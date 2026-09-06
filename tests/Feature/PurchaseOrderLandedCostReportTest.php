<?php

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ErpUpgradeService;
use App\Services\Purchasing\PurchaseOrderCostSync;

/**
 * What a purchase cost, against what it promised to cost.
 *
 * Purchase reporting costed an order by ordered quantity against ordered
 * price. That is the commitment, not the cost: a short delivery still reported
 * the full spend, a price settled at the door was ignored, and freight
 * allocated to the delivery afterwards was invisible — which overstated the
 * planned margin of everything bought by the whole landed cost.
 *
 * The order now carries what its receipts settled it at, and the report shows
 * that against what was ordered.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع الشراء',
        'code' => 'WH-PO-COST',
        'is_active' => true,
        'is_primary' => true,
    ]);

    $this->supplier = Supplier::create(['name' => 'مورّد', 'status' => 'active', 'balance' => 0]);
    $this->carrier = Supplier::create(['name' => 'شركة الشحن', 'status' => 'active', 'balance' => 0]);

    $this->product = Product::create([
        'name_ar' => 'مضخة',
        'sku' => 'SKU-PO-PUMP',
        'price' => 100,
        'cost_price' => 40,
    ]);

    $this->order = function (int $quantity, float $unitPrice) {
        $order = PurchaseOrder::create([
            'order_number' => 'PO-COST-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'status' => 'pending',
            'order_date' => now()->toDateString(),
            'subtotal' => $quantity * $unitPrice,
            'total' => $quantity * $unitPrice,
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'product_name' => $this->product->name_ar,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
        ]);

        return $order->refresh();
    };

    $this->receive = fn (PurchaseOrder $order, int $quantity, float $unitPrice) => $this
        ->actingAs($this->admin)
        ->postJson('/api/v1/purchase-receipts', [
            'purchase_order_id' => $order->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
            ],
        ])->assertCreated();

    $this->report = fn (string $query = '') => $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/reports/purchases'.$query)
        ->assertOk();
});

it('costs an order by what its receipt settled, not by what it asked', function () {
    // Ordered ten at 40; the supplier delivered them at 45.
    $order = ($this->order)(10, 40);
    ($this->receive)($order, 10, 45);

    $row = ($this->report)()->json('data.purchase_orders.0');

    expect((float) $row['ordered_cost'])->toBe(400.0)
        ->and((float) $row['landed_cost'])->toBe(450.0)
        ->and((float) $row['cost_variance'])->toBe(50.0)
        ->and((float) $row['cost_variance_percent'])->toBe(12.5)
        ->and((int) $row['pending_lines'])->toBe(0);
});

it('counts what arrived rather than what was asked for', function () {
    // Ordered ten, six turned up.
    $order = ($this->order)(10, 40);
    ($this->receive)($order, 6, 40);

    $row = ($this->report)()->json('data.purchase_orders.0');

    expect((int) $row['ordered_quantity'])->toBe(10)
        ->and((int) $row['received_quantity'])->toBe(6)
        // 240 of goods, not the 400 the order promised.
        ->and((float) $row['landed_cost'])->toBe(240.0)
        ->and((float) $row['cost_variance'])->toBe(-160.0);
});

it('carries freight allocated after the delivery into what the order cost', function () {
    $order = ($this->order)(10, 40);
    ($this->receive)($order, 10, 40);

    expect((float) ($this->report)()->json('data.purchase_orders.0.landed_cost'))->toBe(400.0);

    // 100 of freight on a delivery of 400 — the goods on that shelf are now
    // worth 500, and the order that bought them should say so.
    $receipt = $order->receipts()->first();
    app(ErpUpgradeService::class)->allocateLandedCost(
        purchaseReceiptId: $receipt->id,
        shipping: 100,
        customs: 0,
        insurance: 0,
        other: 0,
        method: 'value',
        settlement: 'credit',
        supplierId: $this->carrier->id,
    );

    $row = ($this->report)()->json('data.purchase_orders.0');

    expect((float) $row['landed_cost'])->toBe(500.0)
        ->and((float) $row['cost_variance'])->toBe(100.0);
});

it('leaves an order with nothing received at the price it was placed at, and says so', function () {
    ($this->order)(10, 40);

    $row = ($this->report)()->json('data.purchase_orders.0');

    expect((float) $row['ordered_cost'])->toBe(400.0)
        ->and((float) $row['landed_cost'])->toBe(400.0)
        ->and((float) $row['cost_variance'])->toBe(0.0)
        // The two columns agreeing here means "not delivered yet", not
        // "delivered exactly on price", and the flag is the difference.
        ->and((int) $row['pending_lines'])->toBe(1)
        ->and((int) $row['line_count'])->toBe(1);
});

it('sorts by variance so the orders that went over surface first', function () {
    $over = ($this->order)(10, 40);
    ($this->receive)($over, 10, 50);

    $under = ($this->order)(10, 40);
    ($this->receive)($under, 4, 40);

    ($this->order)(10, 40);

    $numbers = fn (string $sort) => array_column(
        ($this->report)('?sort='.$sort)->json('data.purchase_orders'),
        'order_number'
    );

    expect($numbers('variance_desc')[0])->toBe($over->order_number)
        ->and($numbers('variance_asc')[0])->toBe($under->order_number);
});

it('reconciles the page rows against the totals for the whole filtered set', function () {
    $a = ($this->order)(10, 40);
    ($this->receive)($a, 10, 45);
    $b = ($this->order)(5, 20);
    ($this->receive)($b, 5, 20);

    // One row on the page; the summary still describes both orders.
    $response = ($this->report)('?per_page=1');

    expect($response->json('data.purchase_orders'))->toHaveCount(1);
    expect((float) $response->json('data.summary.ordered_cost'))->toBe(500.0)
        ->and((float) $response->json('data.summary.landed_cost'))->toBe(550.0)
        ->and((float) $response->json('data.summary.cost_variance'))->toBe(50.0)
        ->and((int) $response->json('data.summary.pending_lines'))->toBe(0);
});

it('stops overstating the planned margin by the freight it never counted', function () {
    $order = ($this->order)(10, 40);
    $order->items()->update(['sale_price' => 100]);
    ($this->receive)($order, 10, 40);

    $receipt = $order->receipts()->first();
    app(ErpUpgradeService::class)->allocateLandedCost(
        purchaseReceiptId: $receipt->id,
        shipping: 100,
        customs: 0,
        insurance: 0,
        other: 0,
        method: 'value',
        settlement: 'credit',
        supplierId: $this->carrier->id,
    );

    $summary = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/reports/purchases/performance')
        ->assertOk()
        ->json('data.summary');

    // 1000 of planned revenue against 500 of real cost. Costing at the ordered
    // price would have claimed 600 of profit on a 60% margin.
    expect((float) $summary['total_cost'])->toBe(500.0)
        ->and((float) $summary['total_planned_revenue'])->toBe(1000.0)
        ->and((float) $summary['planned_profit'])->toBe(500.0)
        ->and((float) $summary['planned_margin'])->toBe(50.0);
});

it('splits a delivery between two lines of the same product', function () {
    // Receipts name a product, not the line it fills, so an order that lists
    // the same product twice has to divide the delivery between them.
    $order = ($this->order)(6, 40);
    $order->items()->create([
        'product_id' => $this->product->id,
        'product_name' => $this->product->name_ar,
        'quantity' => 2,
        'unit_price' => 40,
        'total_price' => 80,
    ]);

    ($this->receive)($order, 8, 50);

    app(PurchaseOrderCostSync::class)->sync($order->refresh());

    $costs = $order->items()->orderBy('id')->pluck('received_cost')
        ->map(fn ($cost) => (float) $cost)->all();

    // 400 delivered, split six to two.
    expect($costs)->toBe([300.0, 100.0]);
});
