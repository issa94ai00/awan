<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;

/**
 * A purchase order raised for a sale: filled from a sales order or invoice,
 * and linked to it.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->supplier = Supplier::create(['name' => 'مورّد', 'status' => 'active', 'balance' => 0]);
    $this->customer = Customer::create(['name' => 'زبون الواجهة', 'status' => 'active']);

    $this->drain = Product::create(['name_ar' => 'جريدة تصريف', 'sku' => 'FD', 'price' => 5, 'cost_price' => 1]);
    $this->five = ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-5', 'size' => '5"', 'price' => 2, 'cost_price' => 1.5]);
    $this->tap = Product::create(['name_ar' => 'حنفية', 'sku' => 'TAP', 'price' => 20, 'cost_price' => 8]);

    $this->salesOrder = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/sales-orders', [
        'customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->tap->id, 'quantity' => 2, 'unit_price' => 20]],
    ])->assertCreated()->json('data');

    $this->invoice = Invoice::create([
        'invoice_number' => 'INV-LINK-1',
        'customer_id' => $this->customer->id,
        'sales_order_id' => $this->salesOrder['id'],
        'status' => 'sent',
        'subtotal' => 10, 'tax' => 0, 'total' => 10, 'due_amount' => 10,
        'notes' => 'تسليم الأحد',
    ]);
    // One size sold on two lines comes out as one purchase line.
    foreach ([2, 3] as $qty) {
        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->drain->id,
            'product_variant_id' => $this->five->id,
            'product_name' => 'جريدة تصريف - 5"',
            'quantity' => $qty,
            'unit_price' => 2.5,
        ]);
    }

    $this->payload = fn (array $overrides = []) => array_merge([
        'supplier_id' => $this->supplier->id,
        'items' => [['product_id' => $this->tap->id, 'quantity' => 2, 'unit_price' => 8]],
    ], $overrides);
});

it('drafts a purchase order from a sales invoice', function () {
    $draft = $this->getJson("/api/v1/invoices/{$this->invoice->id}/purchase-draft")->assertOk()->json('data');

    expect($draft['invoice']['invoice_number'])->toBe('INV-LINK-1')
        ->and($draft['invoice']['customer_name'])->toBe('زبون الواجهة')
        ->and($draft['invoice']['order_number'])->toBe($this->salesOrder['order_number'])
        ->and($draft['invoice']['notes'])->toBe('تسليم الأحد')
        ->and($draft['items'])->toHaveCount(1);

    $line = $draft['items'][0];
    expect($line['product_variant_id'])->toBe($this->five->id)
        ->and($line['quantity'])->toBe(5)
        ->and((float) $line['sale_price'])->toBe(2.5)
        ->and((float) $line['unit_price'])->toBe(1.5);
});

it('offers no draft for a cancelled invoice', function () {
    $this->invoice->update(['status' => Invoice::STATUS_CANCELLED]);

    $this->getJson("/api/v1/invoices/{$this->invoice->id}/purchase-draft")->assertStatus(422);
});

it('keeps the sale a purchase order was raised for, and names it back', function () {
    $id = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-orders', ($this->payload)(['sales_order_id' => $this->salesOrder['id']]))
        ->assertCreated()
        ->assertJsonPath('data.sales_order.order_number', $this->salesOrder['order_number'])
        ->json('data.id');

    $this->getJson("/api/v1/admin/purchase-orders/{$id}")
        ->assertOk()
        ->assertJsonPath('data.sales_order.customer.name', 'زبون الواجهة');

    // Found by the sale's number, from the purchase list's search box.
    $found = $this->getJson('/api/v1/admin/purchase-orders?search='.$this->salesOrder['order_number'])->json('data.orders');
    expect(collect($found)->pluck('id')->all())->toBe([$id]);

    expect(SalesOrder::find($this->salesOrder['id'])->purchaseOrders()->pluck('id')->all())->toBe([$id]);
});

it('links to an order or an invoice, never both', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-orders', ($this->payload)([
            'sales_order_id' => $this->salesOrder['id'],
            'invoice_id' => $this->invoice->id,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('invoice_id');
});

it('moves the link from an order to an invoice, and can drop it', function () {
    $id = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-orders', ($this->payload)(['sales_order_id' => $this->salesOrder['id']]))
        ->assertCreated()
        ->json('data.id');

    // Only the new link is sent: the old one goes.
    $this->putJson("/api/v1/admin/purchase-orders/{$id}", ($this->payload)(['invoice_id' => $this->invoice->id]))->assertOk();
    $order = PurchaseOrder::find($id);
    expect($order->invoice_id)->toBe($this->invoice->id)->and($order->sales_order_id)->toBeNull();

    $this->putJson("/api/v1/admin/purchase-orders/{$id}", ($this->payload)(['invoice_id' => null, 'sales_order_id' => null]))->assertOk();
    $order->refresh();
    expect($order->invoice_id)->toBeNull()->and($order->sales_order_id)->toBeNull();
});
