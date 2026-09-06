<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * What a shipment cost, recorded on the order it shipped.
 *
 * The order side costed profit the way the invoice side used to: quantity
 * against the product's current cost_price. So an order filled from a cheap
 * batch and one filled from an expensive batch reported the same margin, and
 * re-pricing a product rewrote the reported margin of every order it had ever
 * appeared in — including orders shipped months earlier.
 *
 * The shipment already consumes FIFO layers and knows what the units it took
 * cost; it now writes that onto the line as the goods leave.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workflow = app(SalesOrderWorkflowService::class);

    $this->main = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-SO-COST',
        'is_active' => true,
        'is_primary' => true,
    ]);

    // The catalogue says 100. What was actually paid, below, says otherwise.
    $this->product = Product::create([
        'name_ar' => 'منتج التكلفة',
        'sku' => 'SKU-SO-COST',
        'price' => 250,
        'cost_price' => 100,
    ]);

    $this->customer = Customer::create(['name' => 'عميل التكلفة', 'status' => 'active']);

    $this->receive = function (int $quantity, float $unitCost, ?Warehouse $warehouse = null) {
        app(InventoryService::class)->receive(
            $this->product->id,
            $quantity,
            ($warehouse ?? $this->main)->id,
            ['key' => uniqid('recv-', true), 'unit_cost' => $unitCost]
        );
    };

    $this->order = function (int $quantity) {
        $order = SalesOrder::create([
            'order_number' => 'SO-COST-'.uniqid(),
            'customer_id' => $this->customer->id,
            'status' => SalesOrder::STATUS_PENDING,
            'order_date' => now()->toDateString(),
            'subtotal' => $quantity * 250,
            'total' => $quantity * 250,
            'currency' => 'SYP',
            'fulfillment_warehouse_id' => $this->main->id,
            'created_by' => $this->user->id,
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price' => 250,
        ]);

        return $order->refresh()->load('items');
    };

    $this->ship = function (SalesOrder $order) {
        $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);
        $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
        $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);
    };
});

it('records what the shipped goods cost on the order line', function () {
    // Two batches at two prices: 2 @ 40, then 5 @ 80.
    ($this->receive)(2, 40);
    ($this->receive)(5, 80);

    $this->actingAs($this->user);
    ($this->ship)(($this->order)(3));

    // Oldest first: 40 + 40 + 80. Costing off the catalogue would have said
    // 300, and reported 450 of profit where there was 590.
    expect((float) SalesOrderItem::first()->total_cost)->toBe(160.0)
        ->and(round((float) SalesOrderItem::first()->unit_cost, 5))->toBe(53.33333);
});

it('leaves an unshipped order uncosted rather than guessing on its behalf', function () {
    ($this->receive)(10, 40);

    $this->actingAs($this->user);
    $order = ($this->order)(2);
    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    // Confirmation reserves the stock; it does not spend it. Nothing has cost
    // anything yet, and saying zero would be a claim the order cannot support.
    expect(SalesOrderItem::first()->total_cost)->toBeNull();
});

it('reports the shipped cost rather than re-deriving one from the catalogue', function () {
    ($this->receive)(10, 40);

    $this->actingAs($this->user);
    ($this->ship)(($this->order)(2));

    // The supplier's price moves after the goods have gone. The order does not.
    $this->product->update(['cost_price' => 500]);

    $row = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/admin/reports/sales')
        ->assertOk()
        ->json('data.sales_orders.0');

    expect((float) $row['total_cost'])->toBe(80.0)
        ->and((float) $row['gross_profit'])->toBe(420.0)
        // Nothing here was projected.
        ->and((int) $row['estimated_lines'])->toBe(0)
        ->and((int) $row['uncosted_lines'])->toBe(0);
});
