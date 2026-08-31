<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\Inventory\InventoryService;

/**
 * Bins.vue rendered the warehouse relation directly as a table cell
 * ("[object Object]") and its save payload hardcoded type: 'storage' and
 * capacity_type: 'weight' on every single save — editing any bin that was
 * actually a picking/receiving/etc. bin, or one counted by volume/count
 * rather than weight, silently reclassified it. This pins the backend side
 * down: the list returns a flat warehouse_name, and a partial update only
 * touches the fields it was actually given.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع الصناديق', 'code' => 'WH-BINS', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->bin = WarehouseBin::create([
        'warehouse_id' => $this->warehouse->id,
        'code' => 'BIN-P1',
        'bin_code' => 'BIN-P1',
        'name' => 'Picking Bin 1',
        'type' => 'picking',
        'capacity_type' => 'count',
        'capacity_value' => 500,
        'is_active' => true,
    ]);
});

test('the bin list returns a flat warehouse name, not the relation object', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/wms/bins')
        ->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $this->bin->id);
    expect($row['warehouse_name'])->toBe('مستودع الصناديق');
    expect($row)->not->toHaveKey('warehouse');
});

test('updating only notes does not silently reclassify the bin type or capacity', function () {
    $this->actingAs($this->admin)
        ->putJson("/api/v1/wms/bins/{$this->bin->id}", ['notes' => 'قريب من باب الشحن'])
        ->assertOk();

    $this->bin->refresh();
    expect($this->bin->type)->toBe('picking');
    expect($this->bin->capacity_type)->toBe('count');
    expect((float) $this->bin->capacity_value)->toBe(500.0);
    expect($this->bin->notes)->toBe('قريب من باب الشحن');
});

test('a bin holding inventory cannot be deleted', function () {
    $product = Product::create(['name_ar' => 'صنف', 'sku' => 'SKU-BIN', 'price' => 10, 'cost_price' => 4]);
    app(InventoryService::class)->receive($product->id, 5, $this->warehouse->id, [
        'key' => uniqid(), 'bin_id' => $this->bin->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/wms/bins/{$this->bin->id}")
        ->assertStatus(400);

    expect(WarehouseBin::find($this->bin->id))->not->toBeNull();
});
