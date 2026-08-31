<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

/**
 * POST /api/v1/admin/wms/stock/movements with movement_type=transfer used to
 * be wired to the same code as a plain `out` movement: it debited the source
 * warehouse and never recorded a destination, so a "transfer" silently
 * deleted inventory instead of moving it. This pins the fix down.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->source = Warehouse::create([
        'name' => 'مستودع المصدر', 'code' => 'WH-SRC', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->destination = Warehouse::create([
        'name' => 'مستودع الوجهة', 'code' => 'WH-DST', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'صنبور', 'sku' => 'SKU-TRANSFER', 'price' => 50, 'cost_price' => 20,
    ]);

    app(InventoryService::class)->receive(
        $this->product->id, 30, $this->source->id, ['key' => 'open:'.uniqid()]
    );
});

test('a transfer moves stock into the destination warehouse instead of deleting it', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/wms/stock/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->source->id,
            'to_warehouse_id' => $this->destination->id,
            'movement_type' => 'transfer',
            'quantity' => 12,
        ])
        ->assertOk();

    $sourceQty = WarehouseInventory::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->source->id)->value('quantity');
    $destinationQty = WarehouseInventory::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->destination->id)->value('quantity');

    expect((float) $sourceQty)->toBe(18.0);
    expect((float) $destinationQty)->toBe(12.0);
});

test('a transfer without a destination warehouse is rejected', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/wms/stock/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->source->id,
            'movement_type' => 'transfer',
            'quantity' => 5,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('to_warehouse_id');
});

test('a transfer into the same warehouse it came from is rejected', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/wms/stock/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->source->id,
            'to_warehouse_id' => $this->source->id,
            'movement_type' => 'transfer',
            'quantity' => 5,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('to_warehouse_id');
});
