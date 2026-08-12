<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * Routing an order through several warehouses at once.
 *
 * The screen used to let one warehouse be picked as "the" destination while the
 * per-line sourcing editor offered every active warehouse in the company. So the
 * routing decision and the sourcing decision described different things, and a
 * line could be drawn from a location nobody had chosen to involve.
 *
 * A routing is now a set. Choosing it narrows sourcing to exactly those
 * warehouses — for the screen, for the API, and for the automatic plan built at
 * confirmation.
 */
beforeEach(function () {
    $this->main = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->branch = Warehouse::create([
        'name' => 'فرع دمشق',
        'code' => 'WH-DAM',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $this->far = Warehouse::create([
        'name' => 'فرع حلب',
        'code' => 'WH-ALP',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $this->user = User::factory()->create();
    $this->workflow = app(SalesOrderWorkflowService::class);

    $this->product = Product::create([
        'name_ar' => 'كلادينج',
        'sku' => 'SKU-CLAD',
        'price' => 100,
        'cost_price' => 40,
    ]);

    $this->stock = function (Warehouse $warehouse, int $quantity) {
        WarehouseInventory::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'quarantined_quantity' => 0,
        ]);
    };

    $this->at = fn (Warehouse $w) => WarehouseInventory::where('warehouse_id', $w->id)
        ->where('product_id', $this->product->id)->first();

    $this->order = function (int $quantity = 8, array $attributes = []) {
        $customer = Customer::create([
            'name' => 'عميل',
            'email' => 'routing-multi@example.com',
            'phone' => '+963900000010',
            'status' => 'نشط',
        ]);

        $order = SalesOrder::create(array_merge([
            'order_number' => 'SO-MULTI-'.uniqid(),
            'customer_id' => $customer->id,
            'status' => SalesOrder::STATUS_PENDING,
            'order_date' => now()->toDateString(),
            'subtotal' => 100 * $quantity,
            'total' => 100 * $quantity,
            'currency' => 'SAR',
            'created_by' => $this->user->id,
        ], $attributes));

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price' => 100,
        ]);

        return $order->refresh()->load('items.allocations');
    };

    $this->actingAs($this->user);
});

test('with no routing chosen every warehouse is still offered', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->far, 10);

    $plan = $this->workflow->sourcingPlan(($this->order)());

    // The behaviour before routings existed, kept for every order that has not
    // been narrowed down.
    expect($plan['selected_warehouse_ids'])->toBeNull();
    expect(collect($plan['lines'][0]['sources'])->pluck('warehouse_id')->sort()->values()->all())
        ->toBe([$this->main->id, $this->branch->id, $this->far->id]);
});

test('choosing routings narrows the sources offered for every line', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->far, 10);

    $order = ($this->order)();

    $plan = $this->workflow->saveRoutings($order, [$this->main->id, $this->branch->id]);

    expect($plan['selected_warehouse_ids'])->toEqualCanonicalizing([$this->main->id, $this->branch->id]);

    // The warehouse nobody chose is gone from the per-line editor.
    expect(collect($plan['lines'][0]['sources'])->pluck('warehouse_id')->sort()->values()->all())
        ->toBe([$this->main->id, $this->branch->id]);
});

test('the order can be sourced across all of its chosen routings', function () {
    ($this->stock)($this->main, 3);
    ($this->stock)($this->branch, 5);

    $order = ($this->order)(8);
    $this->workflow->saveRoutings($order, [$this->main->id, $this->branch->id]);

    $item = $order->refresh()->items->first();
    $this->workflow->saveSourcingPlan($order, [
        $item->id => [$this->main->id => 3, $this->branch->id => 5],
    ]);

    $allocations = $order->refresh()->load('items.allocations')
        ->items->first()->allocations->pluck('quantity', 'warehouse_id')
        ->map(fn ($q) => (int) $q)->all();

    expect($allocations[$this->main->id])->toBe(3);
    expect($allocations[$this->branch->id])->toBe(5);
});

test('sourcing from a warehouse outside the routings is refused', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->far, 10);

    $order = ($this->order)(8);
    $this->workflow->saveRoutings($order, [$this->main->id, $this->branch->id]);

    $item = $order->refresh()->items->first();

    // A stale tab or a direct API call must not get past the screen's rule.
    expect(fn () => $this->workflow->saveSourcingPlan($order, [
        $item->id => [$this->far->id => 8],
    ]))->toThrow(RuntimeException::class, 'خارج توجيهات الطلب');
});

test('confirmation plans only across the chosen routings', function () {
    // The unrouted warehouse holds the most, so the automatic plan would reach
    // for it first if the selection were not honoured.
    ($this->stock)($this->main, 3);
    ($this->stock)($this->branch, 5);
    ($this->stock)($this->far, 100);

    $order = ($this->order)(8);
    $this->workflow->saveRoutings($order, [$this->main->id, $this->branch->id]);

    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_CONFIRMED);

    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(3);
    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(5);
    expect((int) ($this->at)($this->far)->reserved_quantity)->toBe(0);
});

test('an order the chosen routings cannot cover is refused rather than widened', function () {
    ($this->stock)($this->main, 2);
    ($this->stock)($this->branch, 2);
    ($this->stock)($this->far, 100);

    $order = ($this->order)(8);
    $this->workflow->saveRoutings($order, [$this->main->id, $this->branch->id]);

    // Reaching into the unchosen warehouse for the shortfall is exactly what the
    // selection exists to prevent, so this stops instead.
    expect(fn () => $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_CONFIRMED))
        ->toThrow(RuntimeException::class, 'ناقص 4 من أصل 8');

    expect((int) ($this->at)($this->far)->reserved_quantity)->toBe(0);
});

test('dropping a routing that still supplies the order is refused', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);

    $order = ($this->order)(8);
    $this->workflow->saveRoutings($order, [$this->main->id, $this->branch->id]);

    $item = $order->refresh()->items->first();
    $this->workflow->saveSourcingPlan($order, [
        $item->id => [$this->main->id => 4, $this->branch->id => 4],
    ]);

    // Silently deleting those four would release stock the order is holding.
    expect(fn () => $this->workflow->saveRoutings($order->refresh(), [$this->main->id]))
        ->toThrow(RuntimeException::class, 'ما زال يزوّد الطلب');
});

test('the owning warehouse follows the selection when it is dropped', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);

    $order = ($this->order)(8, ['fulfillment_warehouse_id' => $this->main->id]);

    $this->workflow->saveRoutings($order, [$this->branch->id]);

    // An unallocated line is picked from the owning warehouse, so it has to stay
    // inside the selection or the plan names a source the shipment ignores.
    expect((int) $order->refresh()->fulfillment_warehouse_id)->toBe($this->branch->id);
});

test('a pickup cannot be routed through more than one branch', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);

    $order = ($this->order)(8, ['fulfillment_type' => SalesOrder::FULFILLMENT_PICKUP]);

    expect(fn () => $this->workflow->saveRoutings($order, [$this->main->id, $this->branch->id]))
        ->toThrow(RuntimeException::class, 'فرع واحد فقط');
});

test('routings cannot be changed once the goods have shipped', function () {
    ($this->stock)($this->main, 10);

    $order = ($this->order)(8, ['fulfillment_warehouse_id' => $this->main->id]);
    $this->workflow->saveRoutings($order, [$this->main->id]);

    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_CONFIRMED);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);

    expect(fn () => $this->workflow->saveRoutings($order->refresh(), [$this->branch->id]))
        ->toThrow(RuntimeException::class, 'بعد شحنه');
});

test('the routing screen marks which warehouses are chosen', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->far, 10);

    $order = ($this->order)(8);
    $this->workflow->saveRoutings($order, [$this->main->id, $this->branch->id]);

    $rows = collect($this->workflow->routingOptions($order->refresh())['warehouses'])
        ->pluck('is_selected', 'warehouse_id');

    expect($rows[$this->main->id])->toBeTrue();
    expect($rows[$this->branch->id])->toBeTrue();
    expect($rows[$this->far->id])->toBeFalse();
});

test('the endpoint saves a multi-warehouse routing', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);

    $order = ($this->order)(8);

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/sales-orders/{$order->id}/routings", [
            'warehouse_ids' => [$this->main->id, $this->branch->id],
        ])
        ->assertOk();

    expect($response->json('data.selected_warehouse_ids'))
        ->toEqualCanonicalizing([$this->main->id, $this->branch->id]);

    expect($order->refresh()->routings->pluck('id')->sort()->values()->all())
        ->toBe([$this->main->id, $this->branch->id]);
});
