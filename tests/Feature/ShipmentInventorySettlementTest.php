<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItemAllocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseInventory;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * Shipping must settle warehouse_inventory: quantity down, reserved cleared,
 * and an OUT movement keyed to the order. Status alone is not enough.
 */
beforeEach(function () {
    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->user = User::factory()->create();
    $this->workflow = app(SalesOrderWorkflowService::class);

    $this->product = Product::create([
        'name_ar' => 'منتج شحن',
        'sku' => 'SKU-SHIP-1',
        'price' => 100,
        'cost_price' => 40,
        'stock_quantity' => 50,
    ]);

    $this->customer = Customer::create([
        'name' => 'عميل الشحن',
        'email' => 'ship@example.com',
        'phone' => '+966500000099',
        'status' => 'نشط',
    ]);

    $this->makeOrder = function (int $quantity = 5) {
        $order = SalesOrder::create([
            'order_number' => 'SO-SHIP-'.uniqid(),
            'customer_id' => $this->customer->id,
            'status' => SalesOrder::STATUS_PENDING,
            'order_date' => now()->toDateString(),
            'subtotal' => $quantity * 100,
            'total' => $quantity * 100,
            'currency' => 'SAR',
            'fulfillment_warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price' => 100,
        ]);

        return $order->refresh()->load('items');
    };

    $this->stock = function (int $quantity, ?int $binId = null) {
        return WarehouseInventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'bin_id' => $binId,
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'quarantined_quantity' => 0,
        ]);
    };
});

test('shipping through the workflow deducts quantity, clears reserved, and records a movement', function () {
    ($this->stock)(20);
    $this->actingAs($this->user);

    $order = ($this->makeOrder)(5);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);
    $row = WarehouseInventory::where('warehouse_id', $this->warehouse->id)
        ->where('product_id', $this->product->id)
        ->first();
    expect((int) $row->reserved_quantity)->toBe(5);
    expect((int) $row->quantity)->toBe(20);

    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);

    $row->refresh();
    expect((int) $row->quantity)->toBe(15);
    expect((int) $row->available_quantity)->toBe(15);
    expect((int) $row->reserved_quantity)->toBe(0);

    $movements = StockMovement::where('reference', 'sales_order')
        ->where('source', (string) $order->id)
        ->get();

    expect($movements)->toHaveCount(1);
    expect((int) $movements->first()->quantity)->toBe(5);
    expect($movements->first()->movement_type)->toBe(StockMovement::TYPE_OUT);

    $order->load('items.allocations');
    $allocations = $order->items->first()->allocations;
    if ($allocations->isNotEmpty()) {
        expect(
            $allocations->every(fn ($a) => $a->status === SalesOrderItemAllocation::STATUS_FULFILLED)
        )->toBeTrue();
    }
});

test('shipping via the transition API settles stock the same way', function () {
    ($this->stock)(20);
    $this->actingAs($this->user);

    $order = ($this->makeOrder)(4);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);

    $this->postJson('/api/v1/sales-orders/'.$order->id.'/transition', [
        'status' => 'shipped',
        'carrier' => 'SMSA',
        'tracking_number' => 'TRK-1',
    ])->assertOk()
        ->assertJsonPath('data.sales_order.status', 'shipped');

    $row = WarehouseInventory::where('warehouse_id', $this->warehouse->id)
        ->where('product_id', $this->product->id)
        ->first();

    expect((int) $row->quantity)->toBe(16);
    expect((int) $row->reserved_quantity)->toBe(0);

    expect(
        StockMovement::where('reference', 'sales_order')->where('source', (string) $order->id)->count()
    )->toBe(1);
});

test('shipping still settles the bin row that held the reservation when an empty warehouse-level row exists', function () {
    // Empty warehouse-level row first (lower id) — the old first() path preferred
    // this and either refused the ship or moved the wrong shelf.
    ($this->stock)(0, null);

    $bin = WarehouseBin::create([
        'warehouse_id' => $this->warehouse->id,
        'bin_code' => 'A-01',
    ]);

    $binRow = ($this->stock)(20, $bin->id);

    $this->actingAs($this->user);
    $order = ($this->makeOrder)(6);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    expect((int) $binRow->refresh()->reserved_quantity)->toBe(6);

    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);

    expect((int) $binRow->refresh()->quantity)->toBe(14);
    expect((int) $binRow->reserved_quantity)->toBe(0);

    expect(
        StockMovement::where('reference', 'sales_order')->where('source', (string) $order->id)->count()
    )->toBe(1);
});

test('enhanced tracking endpoint ships through the workflow instead of flipping status alone', function () {
    ($this->stock)(20);
    $this->actingAs($this->user);

    $order = ($this->makeOrder)(3);
    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);

    $this->postJson('/api/v1/sales-orders/enhanced/'.$order->id.'/tracking', [
        'tracking_number' => 'TRK-ENH-1',
        'carrier' => 'Aramex',
    ])->assertOk()
        ->assertJsonPath('data.status', 'shipped');

    $row = WarehouseInventory::where('warehouse_id', $this->warehouse->id)
        ->where('product_id', $this->product->id)
        ->first();

    expect((int) $row->quantity)->toBe(17);
    expect((int) $row->reserved_quantity)->toBe(0);
    expect(
        StockMovement::where('reference', 'sales_order')->where('source', (string) $order->id)->count()
    )->toBe(1);
});
