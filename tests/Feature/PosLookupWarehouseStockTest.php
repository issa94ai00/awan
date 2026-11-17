<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;

/**
 * The order form's product search says where each result can ship from.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->main = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-M', 'is_active' => true]);
    $this->branch = Warehouse::create(['name' => 'الفرع', 'code' => 'WH-B', 'is_active' => true]);
    $this->empty = Warehouse::create(['name' => 'فارغ', 'code' => 'WH-E', 'is_active' => true]);
    $this->product = Product::factory()->create(['sku' => 'WH-LOOKUP-1', 'is_active' => 1]);

    $inv = app(InventoryService::class);
    $inv->receive($this->product->id, 2, $this->main->id, ['key' => 'm']);
    $inv->receive($this->product->id, 5, $this->branch->id, ['key' => 'b']);
    // Held for a confirmed order: not sellable.
    $inv->reserve($this->product->id, 1, $this->branch->id);
});

it('lists sellable stock per warehouse, largest first, when asked', function () {
    $rows = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/pos/products/lookup?q=WH-LOOKUP-1&expand_variants=1&with_warehouses=1')
        ->assertOk()
        ->json('data.0.warehouse_stock');

    expect($rows)->toBe([
        ['warehouse_id' => $this->branch->id, 'name' => 'الفرع', 'available' => 4],
        ['warehouse_id' => $this->main->id, 'name' => 'الرئيسي', 'available' => 2],
    ]);
});

it('leaves the payload unchanged for callers that do not ask', function () {
    $row = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/pos/products/lookup?q=WH-LOOKUP-1')
        ->assertOk()
        ->json('data.0');

    expect($row)->not->toHaveKey('warehouse_stock');
});
