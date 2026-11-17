<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * Cost has to survive the hops between documents.
 *
 * Two hops lost it: a warehouse transfer received its units at 0 (the out-leg
 * knew the FIFO cost, the in-leg ignored it), and an invoice raised from a
 * sales order never learned the cost its order lines were stamped with when
 * the goods shipped.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->from = Warehouse::create(['name' => 'المصدر', 'code' => 'WH-FROM', 'is_active' => true]);
    $this->to = Warehouse::create(['name' => 'الوجهة', 'code' => 'WH-TO', 'is_active' => true]);
    $this->product = Product::factory()->create(['price' => 100, 'cost_price' => 0]);
    $this->inventory = app(InventoryService::class);
});

it('carries the source cost into the destination on a transfer', function () {
    $this->inventory->receive($this->product->id, 3, $this->from->id, ['key' => 'r1', 'unit_cost' => 40]);

    [, $in] = $this->inventory->transfer($this->product->id, 3, $this->from->id, $this->to->id, ['key' => 't1']);

    expect((float) $in->unit_cost)->toBe(40.0);

    // Selling from the destination now costs 40 a unit, not 0.
    $out = $this->inventory->issue($this->product->id, 2, $this->to->id, ['key' => 'sale1']);
    expect((float) $out->total_cost)->toBe(80.0);
});

it('copies order line cost onto the invoice raised from the order', function () {
    $customer = Customer::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'status' => 'active']);
    $order = SalesOrder::create([
        'order_number' => 'SO-COST-1', 'customer_id' => $customer->id,
        'fulfillment_warehouse_id' => $this->from->id, 'status' => SalesOrder::STATUS_PENDING,
        'subtotal' => 200, 'tax' => 0, 'discount' => 0, 'total' => 200, 'currency' => 'SYP',
    ]);
    $line = $order->items()->create(['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100, 'discount' => 0, 'tax' => 0]);

    $service = app(SalesOrderWorkflowService::class);
    $invoice = $service->ensureInvoice($order);
    expect($invoice->items()->first()->unit_cost)->toBeNull();

    // Shipment stamps the order line…
    $line->forceFill(['unit_cost' => 35, 'total_cost' => 70])->save();

    // …and the invoice line follows.
    expect($service->syncInvoiceLineCosts($order->fresh()))->toBe(1);
    $invoiceLine = $invoice->items()->first();
    expect((float) $invoiceLine->unit_cost)->toBe(35.0)
        ->and((float) $invoiceLine->total_cost)->toBe(70.0);
});
