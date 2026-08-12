<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * Where an order goes when nobody said.
 *
 * An order that reaches confirmation without an explicit warehouse used to be
 * pinned to the lowest-numbered active warehouse — the main one — at the moment
 * it was created. By the time confirmation ran the field was already filled, so
 * the coverage-based routing never got a say: an order for goods that only the
 * branch holds was routed to the main warehouse and refused for lack of stock.
 *
 * These tests fix the decision at confirmation, where the stock figures are
 * known, rather than at creation, where they are not.
 */
beforeEach(function () {
    // Created first, so it holds the lowest id — the "first active warehouse"
    // that the old default would have picked, exactly as in production.
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

    $this->user = User::factory()->create();
    $this->workflow = app(SalesOrderWorkflowService::class);

    $this->product = Product::create([
        'name_ar' => 'مضخة مياه',
        'sku' => 'SKU-PUMP',
        'price' => 100,
        'cost_price' => 60,
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

    $this->customer = Customer::create([
        'name' => 'عميل',
        'email' => 'routing@example.com',
        'phone' => '+963900000001',
        'status' => 'نشط',
    ]);

    // An order as the web/admin path builds one: no warehouse named, no split
    // planned, just a customer and a line.
    $this->unroutedOrder = function (int $quantity = 8, array $attributes = []) {
        $order = SalesOrder::create(array_merge([
            'order_number' => 'SO-ROUTE-'.uniqid(),
            'customer_id' => $this->customer->id,
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

test('an order is routed to the warehouse that actually holds the goods', function () {
    // Only the branch can serve this order.
    ($this->stock)($this->main, 0);
    ($this->stock)($this->branch, 10);

    $order = ($this->unroutedOrder)(8);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    expect((int) $order->refresh()->fulfillment_warehouse_id)->toBe($this->branch->id);
    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(8);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(0);
});

test('an order no single warehouse can cover is split across the ones that can', function () {
    ($this->stock)($this->main, 3);
    ($this->stock)($this->branch, 5);

    $order = ($this->unroutedOrder)(8);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    $order->refresh()->load('items.allocations');

    // Deepest stock first — the main warehouse gives what it has, the branch
    // covers the rest. Matches the suggestion engine's "main warehouse first"
    // contract that the mobile app already shows staff.
    $plan = $order->items->first()->allocations
        ->pluck('quantity', 'warehouse_id')
        ->map(fn ($q) => (int) $q)
        ->all();

    expect($plan[$this->main->id])->toBe(3);
    expect($plan[$this->branch->id])->toBe(5);
    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(5);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(3);
});

test('a split order ships each share out of its own warehouse', function () {
    ($this->stock)($this->main, 3);
    ($this->stock)($this->branch, 5);

    $order = ($this->unroutedOrder)(8);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);

    $out = StockMovement::where('reference', 'sales_order')
        ->where('source', $order->id)
        ->get();

    $shipped = $out->pluck('quantity', 'warehouse_id')->map(fn ($q) => (int) $q)->all();

    expect($shipped[$this->main->id])->toBe(3);
    expect($shipped[$this->branch->id])->toBe(5);

    expect((int) ($this->at)($this->branch)->available_quantity)->toBe(0);
    expect((int) ($this->at)($this->main)->available_quantity)->toBe(0);
});

test('a warehouse the operator chose is honoured over the recommendation', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);

    // Both could serve it; the operator named the main warehouse.
    $order = ($this->unroutedOrder)(8, ['fulfillment_warehouse_id' => $this->main->id]);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    expect((int) $order->refresh()->fulfillment_warehouse_id)->toBe($this->main->id);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(8);
    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(0);
});

test('a plan already recorded on the lines is left alone', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);

    $order = ($this->unroutedOrder)(8);
    $item = $order->items->first();
    $item->allocations()->create(['warehouse_id' => $this->branch->id, 'quantity' => 6]);
    $item->allocations()->create(['warehouse_id' => $this->main->id, 'quantity' => 2]);

    $this->workflow->transitionTo($order->refresh()->load('items.allocations'), SalesOrder::STATUS_CONFIRMED);

    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(6);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(2);
});

test('a branch that cannot fill its own order keeps it and draws the goods from elsewhere', function () {
    // The branch sold something it does not have on the shelf.
    ($this->stock)($this->branch, 0);
    ($this->stock)($this->main, 10);

    $order = ($this->unroutedOrder)(8, ['fulfillment_warehouse_id' => $this->branch->id]);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    $order->refresh()->load('items.allocations');

    // The order is still the branch's — it is their customer and their sale —
    // while the units are held where they actually are.
    expect((int) $order->fulfillment_warehouse_id)->toBe($this->branch->id);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(8);
    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(0);

    // And the line says so, rather than leaving it to a fallback that would
    // follow the order if it were ever re-routed.
    expect($order->items->first()->allocations->pluck('quantity', 'warehouse_id')
        ->map(fn ($q) => (int) $q)->all())->toBe([$this->main->id => 8]);
});

test('one warehouse that can cover it all beats a split', function () {
    // The branch could contribute 5 of the 8, but the main warehouse can do the
    // whole thing — and one pick and one shipment costs less than two.
    ($this->stock)($this->branch, 5);
    ($this->stock)($this->main, 10);

    $order = ($this->unroutedOrder)(8, ['fulfillment_warehouse_id' => $this->branch->id]);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(8);
    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(0);
});

test('a split draws from the order\'s own warehouse before anywhere else', function () {
    // Neither can cover 8 alone, so it splits. The branch is the order's own
    // warehouse and gives everything it has first — even though the main
    // warehouse is the primary one and would otherwise lead.
    ($this->stock)($this->branch, 5);
    ($this->stock)($this->main, 5);

    $order = ($this->unroutedOrder)(8, ['fulfillment_warehouse_id' => $this->branch->id]);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(5);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(3);
});

test('a pickup no single branch can cover is refused rather than split', function () {
    // Splitting would send the customer to two counters for one collection.
    ($this->stock)($this->main, 3);
    ($this->stock)($this->branch, 5);

    $order = ($this->unroutedOrder)(8, ['fulfillment_type' => SalesOrder::FULFILLMENT_PICKUP]);

    expect(fn () => $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED))
        ->toThrow(RuntimeException::class, 'لا يمكن تقسيم طلب استلام');

    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(0);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(0);
    expect($order->refresh()->status)->toBe(SalesOrder::STATUS_PENDING);
});

test('an order the whole network cannot cover is refused against the network', function () {
    ($this->stock)($this->main, 2);
    ($this->stock)($this->branch, 3);

    $order = ($this->unroutedOrder)(8);

    // The shortfall is stated once, against everything there is — not as a
    // complaint about one warehouse the operator never chose.
    expect(fn () => $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED))
        ->toThrow(RuntimeException::class, 'ناقص 3 من أصل 8');

    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(0);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(0);
});

test('two lines of one product cannot be promised the same units', function () {
    // Six on hand in total, two lines of four. Weighed line by line each looks
    // affordable; together they are not.
    ($this->stock)($this->main, 6);
    ($this->stock)($this->branch, 0);

    $order = ($this->unroutedOrder)(4);
    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity' => 4,
        'unit_price' => 100,
    ]);

    expect(fn () => $this->workflow->transitionTo($order->refresh()->load('items.allocations'), SalesOrder::STATUS_CONFIRMED))
        ->toThrow(RuntimeException::class, 'الرصيد غير كافٍ');

    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(0);
});

test('a split order cannot be turned into a branch collection', function () {
    ($this->stock)($this->main, 3);
    ($this->stock)($this->branch, 5);

    $order = ($this->unroutedOrder)(8);
    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    expect(fn () => $this->workflow->changeFulfillmentType($order->refresh(), SalesOrder::FULFILLMENT_PICKUP))
        ->toThrow(RuntimeException::class, 'مقسّم على 2 مستودعات');

    // Refused outright: the type, the hold and the split all stand.
    expect($order->refresh()->fulfillment_type)->not->toBe(SalesOrder::FULFILLMENT_PICKUP);
    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(5);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(3);
});

test('the stage history records which warehouses served the order', function () {
    ($this->stock)($this->main, 3);
    ($this->stock)($this->branch, 5);

    $order = ($this->unroutedOrder)(8);

    $result = $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    expect($result['effects']['routing']['split'])->toBeTrue();
    expect($result['effects']['routing']['sources'])->toBe([
        $this->branch->id => 5,
        $this->main->id => 3,
    ]);
});

test('the seller\'s own branch serves the order when it can', function () {
    ($this->stock)($this->main, 10);
    ($this->stock)($this->branch, 10);

    $employee = Employee::create([
        'name' => 'بائع الفرع',
        'email' => 'seller@example.com',
        'phone' => '+963900000002',
        'position' => 'بائع',
        'hire_date' => now()->toDateString(),
        'salary' => 1000,
        'warehouse_id' => $this->branch->id,
    ]);

    $order = ($this->unroutedOrder)(8, ['assigned_employee_id' => $employee->id]);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    expect((int) $order->refresh()->fulfillment_warehouse_id)->toBe($this->branch->id);
    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(8);
});
