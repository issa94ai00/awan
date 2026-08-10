<?php

use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;

/**
 * The field app's branch-first sourcing rule.
 *
 * A seller sells out of their own branch. When the branch falls short, the main
 * warehouse may make up the difference — but only if the seller says so, and the
 * added quantity becomes a line of its own naming where it came from.
 */
beforeEach(function () {
    $this->main = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->branch = Warehouse::create([
        'name' => 'فرع جدة',
        'code' => 'WH-JED',
        'location' => 'جدة',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => false,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $this->seller = User::factory()->create();

    Employee::create([
        'name' => 'بائع الفرع',
        'email' => 'branch.seller@example.com',
        'phone' => '+966500000001',
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'salary' => 4000,
        'status' => 'نشط',
        'user_id' => $this->seller->id,
        'warehouse_id' => $this->branch->id,
    ]);

    $this->product = Product::create([
        'name_ar' => 'خلاط مغسلة',
        'sku' => 'SKU-MIXER',
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

    $this->order = fn (array $payload) => $this->actingAs($this->seller, 'sanctum')
        ->postJson('/api/v1/field/orders', array_merge([
            'customer_name' => 'عميل الفرع',
            'customer_phone' => '+966500000009',
        ], $payload));
});

test('an order the branch can cover is created without a supply line', function () {
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->main, 50);

    $response = ($this->order)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 4]],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.has_supply_lines', false)
        ->assertJsonCount(1, 'data.lines');

    $order = SalesOrder::latest('id')->first();
    expect($order->items)->toHaveCount(1);
    expect((int) $order->items->first()->quantity)->toBe(4);

    // Even the ordinary case records where it is picked from.
    expect((int) $order->items->first()->allocations->first()->warehouse_id)
        ->toBe($this->branch->id);
});

test('a shortfall is refused with the sourcing breakdown until the seller agrees', function () {
    ($this->stock)($this->branch, 3);
    ($this->stock)($this->main, 50);

    $response = ($this->order)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 10]],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('data.reason', 'supply_confirmation_required')
        ->assertJsonPath('data.sourcing.needs_supply', true)
        ->assertJsonPath('data.sourcing.can_fulfil', true)
        ->assertJsonPath('data.sourcing.lines.0.from_branch', 3)
        ->assertJsonPath('data.sourcing.lines.0.from_supply', 7)
        ->assertJsonPath('data.sourcing.supply_warehouse.name', 'المستودع الرئيسي');

    // Refused means refused: nothing was written on the way to asking.
    expect(SalesOrder::count())->toBe(0);
});

test('agreeing adds a second line carrying the top-up and the main warehouse name', function () {
    ($this->stock)($this->branch, 3);
    ($this->stock)($this->main, 50);

    $response = ($this->order)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 10]],
        'supply_from_main' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.has_supply_lines', true)
        ->assertJsonCount(2, 'data.lines');

    $order = SalesOrder::with('items.allocations')->latest('id')->first();

    $branchLine = $order->items->firstWhere('description', null);
    $supplyLine = $order->items->first(fn ($item) => $item->description !== null);

    expect((int) $branchLine->quantity)->toBe(3);
    expect((int) $branchLine->allocations->first()->warehouse_id)->toBe($this->branch->id);

    expect((int) $supplyLine->quantity)->toBe(7);
    expect($supplyLine->description)->toContain('المستودع الرئيسي');
    expect((int) $supplyLine->allocations->first()->warehouse_id)->toBe($this->main->id);

    // Both halves are priced the same — the top-up is the same goods.
    expect((float) $supplyLine->unit_price)->toBe((float) $branchLine->unit_price);
    // And the order is still billed for what the customer asked for.
    expect((float) $order->subtotal)->toBe(1000.0);
});

test('a topped-up order confirms, holding each half where it will be picked', function () {
    ($this->stock)($this->branch, 3);
    ($this->stock)($this->main, 50);

    ($this->order)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 10]],
        'supply_from_main' => true,
        'confirm' => true,
    ])->assertStatus(201);

    $order = SalesOrder::latest('id')->first();
    expect($order->status)->toBe(SalesOrder::STATUS_CONFIRMED);

    // The reservation follows the plan rather than the order's own warehouse:
    // 3 held at the branch, 7 at the main store.
    expect((int) WarehouseInventory::where('warehouse_id', $this->branch->id)
        ->where('product_id', $this->product->id)->value('reserved_quantity'))->toBe(3);

    expect((int) WarehouseInventory::where('warehouse_id', $this->main->id)
        ->where('product_id', $this->product->id)->value('reserved_quantity'))->toBe(7);
});

test('a quantity neither warehouse can reach is refused outright', function () {
    ($this->stock)($this->branch, 3);
    ($this->stock)($this->main, 4);

    $response = ($this->order)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 20]],
        'supply_from_main' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('data.reason', 'insufficient_stock')
        ->assertJsonPath('data.sourcing.can_fulfil', false)
        ->assertJsonPath('data.sourcing.lines.0.unavailable', 13);

    expect(SalesOrder::count())->toBe(0);
});

test('the preview reports the split without reserving anything', function () {
    ($this->stock)($this->branch, 3);
    ($this->stock)($this->main, 50);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson('/api/v1/field/orders/preview', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 10]],
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.sourcing.lines.0.from_branch', 3)
        ->assertJsonPath('data.sourcing.lines.0.from_supply', 7)
        ->assertJsonPath('data.sourcing.needs_supply', true);

    expect((int) WarehouseInventory::where('warehouse_id', $this->branch->id)
        ->where('product_id', $this->product->id)->value('reserved_quantity'))->toBe(0);
    expect((int) WarehouseInventory::where('warehouse_id', $this->main->id)
        ->where('product_id', $this->product->id)->value('reserved_quantity'))->toBe(0);
});

test('stock already held for another order is not offered twice', function () {
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->main, 50);

    // Someone else is holding 8 of the branch's 10.
    WarehouseInventory::where('warehouse_id', $this->branch->id)
        ->where('product_id', $this->product->id)
        ->update(['reserved_quantity' => 8]);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson('/api/v1/field/orders/preview', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.sourcing.lines.0.branch_available', 2)
        ->assertJsonPath('data.sourcing.lines.0.from_branch', 2)
        ->assertJsonPath('data.sourcing.lines.0.from_supply', 3);
});

test('repeated lines for one product are measured against one shelf', function () {
    ($this->stock)($this->branch, 5);
    ($this->stock)($this->main, 50);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson('/api/v1/field/orders/preview', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5],
                ['product_id' => $this->product->id, 'quantity' => 5],
            ],
        ])
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.sourcing.lines')
        ->assertJsonPath('data.sourcing.lines.0.requested', 10)
        ->assertJsonPath('data.sourcing.lines.0.from_branch', 5)
        ->assertJsonPath('data.sourcing.lines.0.from_supply', 5);
});
