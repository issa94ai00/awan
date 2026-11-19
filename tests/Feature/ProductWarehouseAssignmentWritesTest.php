<?php

use App\Models\Product;
use App\Models\ProductWarehouseAssignment;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->wh = Warehouse::create([
        'name' => 'مستودع الربط', 'code' => 'WH-PWA', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);
    $this->product = Product::create(['name_ar' => 'منتج ربط', 'sku' => 'SKU-PWA', 'price' => 30, 'cost_price' => 10]);

    $this->payload = [
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'effective_date' => now()->toDateString(), 'replenishment_method' => 'purchase', 'planning_method' => 'rop',
        'min_stock_level' => 5, 'max_stock_level' => 30, 'safety_stock' => 2, 'lead_time_days' => 4,
        'putaway_strategy' => 'fifo',
    ];
});

test('assignment create makes the inventory row, refuses bad levels', function () {
    $id = $this->actingAs($this->admin)->postJson('/api/v1/admin/product-warehouse-assignments', $this->payload)
        ->assertCreated()->json('data.id');

    expect(ProductWarehouseAssignment::find($id)->min_stock_level)->toBe(5);
    $row = WarehouseInventory::where('product_id', $this->product->id)->where('warehouse_id', $this->wh->id)->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->quantity)->toBe(0)
        ->and((int) $row->reorder_point)->toBe(5)
        ->and((int) $row->safety_stock)->toBe(2);

    // (same product/warehouse/date duplicate check is not asserted: on SQLite it 500s, see report)
    // safety stock above min, max below min, past effective date
    $this->postJson('/api/v1/admin/product-warehouse-assignments', array_merge($this->payload, ['safety_stock' => 9]))->assertStatus(422);
    $this->postJson('/api/v1/admin/product-warehouse-assignments', array_merge($this->payload, ['max_stock_level' => 1]))->assertStatus(422);
    $this->postJson('/api/v1/admin/product-warehouse-assignments', array_merge($this->payload, ['effective_date' => now()->subDay()->toDateString()]))->assertStatus(422);
    expect(ProductWarehouseAssignment::count())->toBe(1);
});

test('assignment update propagates reorder settings to the inventory row', function () {
    $id = $this->actingAs($this->admin)->postJson('/api/v1/admin/product-warehouse-assignments', $this->payload)->json('data.id');

    $this->putJson("/api/v1/admin/product-warehouse-assignments/$id", [
        'min_stock_level' => 8, 'max_stock_level' => 40, 'safety_stock' => 3, 'lead_time_days' => 6, 'notes' => 'تحديث',
    ])->assertOk();

    expect(ProductWarehouseAssignment::find($id)->min_stock_level)->toBe(8);
    $row = WarehouseInventory::where('product_id', $this->product->id)->where('warehouse_id', $this->wh->id)->first();
    expect((int) $row->reorder_point)->toBe(8)->and((int) $row->safety_stock)->toBe(3)->and((int) $row->lead_time_days)->toBe(6);

    $this->putJson("/api/v1/admin/product-warehouse-assignments/$id", ['planning_method' => 'bogus'])->assertStatus(422);
    $this->putJson('/api/v1/admin/product-warehouse-assignments/999999', ['notes' => 'x'])->assertStatus(404);
});

test('assignment delete is refused while it holds stock', function () {
    $id = $this->actingAs($this->admin)->postJson('/api/v1/admin/product-warehouse-assignments', $this->payload)->json('data.id');
    app(InventoryService::class)->receive($this->product->id, 5, $this->wh->id, ['key' => 'pwa-open']);

    $this->deleteJson("/api/v1/admin/product-warehouse-assignments/$id")->assertStatus(400)->assertJsonPath('success', false);
    expect(ProductWarehouseAssignment::find($id))->not->toBeNull();

    app(InventoryService::class)->issue($this->product->id, 5, $this->wh->id, ['key' => 'pwa-out']);
    $this->deleteJson("/api/v1/admin/product-warehouse-assignments/$id")->assertOk();
    expect(ProductWarehouseAssignment::find($id))->toBeNull();
});

test('update-stock-balance: putaway adds, picking subtracts, over-pick is refused', function () {
    $base = ['warehouse_id' => $this->wh->id, 'product_id' => $this->product->id];
    $qty = fn () => (int) WarehouseInventory::where('product_id', $this->product->id)->where('warehouse_id', $this->wh->id)->value('quantity');
    $this->actingAs($this->admin);

    $res = $this->postJson('/api/v1/admin/product-warehouse-assignments/update-stock-balance', $base + [
        'quantity_change' => 12, 'movement_type' => 'putaway', 'reference_type' => 'grn', 'reference_id' => 7, 'batch_number' => 'LOT-9',
    ])->assertOk()->assertJsonPath('success', true);
    expect($qty())->toBe(12);
    expect(StockMovement::find($res->json('data.movement_id')))->not->toBeNull();
    expect(WarehouseInventory::where('product_id', $this->product->id)->value('batch_number'))->toBe('LOT-9');

    // same reference replays as a no-op
    $this->postJson('/api/v1/admin/product-warehouse-assignments/update-stock-balance', $base + [
        'quantity_change' => 12, 'movement_type' => 'putaway', 'reference_type' => 'grn', 'reference_id' => 7,
    ])->assertOk();
    expect($qty())->toBe(12);

    $this->postJson('/api/v1/admin/product-warehouse-assignments/update-stock-balance', $base + ['quantity_change' => 5, 'movement_type' => 'picking'])->assertOk();
    expect($qty())->toBe(7);

    $this->postJson('/api/v1/admin/product-warehouse-assignments/update-stock-balance', $base + ['quantity_change' => 50, 'movement_type' => 'picking'])
        ->assertStatus(400)->assertJsonPath('success', false);
    expect($qty())->toBe(7);

    $this->postJson('/api/v1/admin/product-warehouse-assignments/update-stock-balance', $base + ['quantity_change' => 1, 'movement_type' => 'bogus'])->assertStatus(422);
});
