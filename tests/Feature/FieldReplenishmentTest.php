<?php

use App\Models\Employee;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;

/**
 * Restocking a branch from the main warehouse.
 *
 * The branch needs to know what it is running out of and what the main
 * warehouse can actually send, before it asks — and a request covering several
 * products must report every problem at once rather than one per attempt.
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

    $this->product = function (string $name) {
        return Product::create([
            'name_ar' => $name,
            'sku' => 'SKU-' . str_replace(' ', '', $name),
            'price' => 100,
            'cost_price' => 60,
        ]);
    };

    $this->stock = function (Warehouse $warehouse, Product $product, int $quantity, int $reorderPoint = 0) {
        WarehouseInventory::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'quarantined_quantity' => 0,
            'reorder_point' => $reorderPoint,
        ]);
    };

    $this->suggestions = fn () => $this->actingAs($this->seller, 'sanctum')
        ->getJson('/api/v1/field/replenishment/suggestions');
});

test('suggestions list what is out or below the reorder point, and nothing else', function () {
    $healthy = ($this->product)('صنف سليم');
    $low = ($this->product)('صنف منخفض');
    $out = ($this->product)('صنف نافد');

    ($this->stock)($this->branch, $healthy, 50, 10);
    ($this->stock)($this->branch, $low, 4, 10);
    ($this->stock)($this->branch, $out, 0, 10);

    ($this->stock)($this->main, $low, 100);
    ($this->stock)($this->main, $out, 100);

    $response = ($this->suggestions)();

    $response->assertStatus(200)
        ->assertJsonPath('data.summary.total', 2)
        ->assertJsonPath('data.summary.out_of_stock', 1)
        ->assertJsonPath('data.summary.low_stock', 1)
        // Empty shelves come first: that is the order a person works through.
        ->assertJsonPath('data.items.0.product_id', $out->id)
        ->assertJsonPath('data.items.0.urgency', 'out')
        ->assertJsonPath('data.items.1.urgency', 'low');

    $listed = collect($response->json('data.items'))->pluck('product_id');
    expect($listed)->not->toContain($healthy->id);
});

test('each line suggests restocking to the level worth holding', function () {
    $low = ($this->product)('صنف منخفض');
    ($this->stock)($this->branch, $low, 4, 10);
    ($this->stock)($this->main, $low, 100);

    ($this->suggestions)()
        ->assertStatus(200)
        ->assertJsonPath('data.items.0.available', 4)
        ->assertJsonPath('data.items.0.reorder_point', 10)
        ->assertJsonPath('data.items.0.target_level', 10)
        // 10 worth holding, 4 on the shelf.
        ->assertJsonPath('data.items.0.suggested_quantity', 6);
});

test('a line the main warehouse cannot cover is flagged before it is submitted', function () {
    $short = ($this->product)('صنف شحيح');
    ($this->stock)($this->branch, $short, 1, 20);
    ($this->stock)($this->main, $short, 5);

    ($this->suggestions)()
        ->assertStatus(200)
        ->assertJsonPath('data.items.0.suggested_quantity', 19)
        ->assertJsonPath('data.items.0.supply_available', 5)
        // What could actually be sent of the suggestion.
        ->assertJsonPath('data.items.0.supply_covers', 5)
        ->assertJsonPath('data.items.0.is_covered', false)
        ->assertJsonPath('data.summary.not_covered', 1);
});

test('stock held for another order is not offered as available to send', function () {
    $item = ($this->product)('صنف محجوز');
    ($this->stock)($this->branch, $item, 2, 10);
    ($this->stock)($this->main, $item, 30);

    WarehouseInventory::where('warehouse_id', $this->main->id)
        ->where('product_id', $item->id)
        ->update(['reserved_quantity' => 25]);

    ($this->suggestions)()
        ->assertStatus(200)
        ->assertJsonPath('data.items.0.supply_available', 5);
});

test('one request covers every line rather than one transfer per product', function () {
    $first = ($this->product)('صنف أول');
    $second = ($this->product)('صنف ثان');

    ($this->stock)($this->branch, $first, 1, 10);
    ($this->stock)($this->branch, $second, 2, 10);
    ($this->stock)($this->main, $first, 50);
    ($this->stock)($this->main, $second, 50);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson('/api/v1/field/replenishment', [
            'items' => [
                ['product_id' => $first->id, 'quantity' => 9],
                ['product_id' => $second->id, 'quantity' => 8],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonCount(2, 'data.request.items');

    expect(InventoryTransfer::count())->toBe(1);

    // The source holds what it has promised, so the same units cannot be sold
    // out from under the request while it waits to be shipped.
    expect((int) WarehouseInventory::where('warehouse_id', $this->main->id)
        ->where('product_id', $first->id)->value('reserved_quantity'))->toBe(9);
    expect((int) WarehouseInventory::where('warehouse_id', $this->main->id)
        ->where('product_id', $second->id)->value('reserved_quantity'))->toBe(8);
});

test('a refused request names every short line, not just the first', function () {
    $first = ($this->product)('صنف أول');
    $second = ($this->product)('صنف ثان');
    $third = ($this->product)('صنف ثالث');

    ($this->stock)($this->branch, $first, 1, 10);
    ($this->stock)($this->main, $first, 2);
    ($this->stock)($this->main, $second, 3);
    ($this->stock)($this->main, $third, 100);

    $response = $this->actingAs($this->seller, 'sanctum')
        ->postJson('/api/v1/field/replenishment', [
            'items' => [
                ['product_id' => $first->id, 'quantity' => 50],
                ['product_id' => $second->id, 'quantity' => 50],
                ['product_id' => $third->id, 'quantity' => 5],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('data.reason', 'insufficient_source_stock')
        // Both short lines, in one answer — the third is fine and not listed.
        ->assertJsonCount(2, 'data.shortfalls')
        ->assertJsonPath('data.shortfalls.0.available', 2)
        ->assertJsonPath('data.shortfalls.0.shortfall', 48)
        ->assertJsonPath('data.shortfalls.1.available', 3);

    // Refused means nothing was written and nothing was held.
    expect(InventoryTransfer::count())->toBe(0);
    expect((int) WarehouseInventory::where('warehouse_id', $this->main->id)
        ->where('product_id', $third->id)->value('reserved_quantity'))->toBe(0);
});

test('a branch with healthy stock is told so rather than shown an empty list', function () {
    $healthy = ($this->product)('صنف سليم');
    ($this->stock)($this->branch, $healthy, 80, 10);

    ($this->suggestions)()
        ->assertStatus(200)
        ->assertJsonPath('data.summary.total', 0)
        ->assertJsonPath('message', 'لا توجد أصناف تحتاج تزويداً في مستودعك.');
});

test('the seller cannot ask for suggestions about a warehouse outside their scope', function () {
    $this->actingAs($this->seller, 'sanctum')
        ->getJson('/api/v1/field/replenishment/suggestions?warehouse_id=' . $this->main->id)
        ->assertStatus(403);
});
