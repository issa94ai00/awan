<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;

/**
 * The dashboard's "top consumed products" widget summed current inventory
 * balance, not units actually shipped out — a product ranked #1 there was
 * the one holding the most stock, closer to the opposite of what "most
 * consumed" promises. This pins the real fix (StockMovement 'out' over the
 * last 30 days) down: a product with a huge balance but no sales must not
 * outrank one that is actually moving.
 */
test('top consumed products reflects real issue movements, not current stock level', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'مستودع اللوحة', 'code' => 'WH-DASH', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $hoarded = Product::create(['name_ar' => 'صنف مكدّس', 'sku' => 'SKU-HOARD', 'price' => 10, 'cost_price' => 4]);
    $moving = Product::create(['name_ar' => 'صنف رائج', 'sku' => 'SKU-MOVE', 'price' => 10, 'cost_price' => 4]);

    $inventory = app(InventoryService::class);
    // Huge balance, never sold.
    $inventory->receive($hoarded->id, 1000, $warehouse->id, ['key' => uniqid()]);
    // Small balance, but actually moving.
    $inventory->receive($moving->id, 50, $warehouse->id, ['key' => uniqid()]);
    $inventory->issue($moving->id, 40, $warehouse->id, ['key' => uniqid()]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/admin/wms/dashboard')
        ->assertOk();

    $names = collect($response->json('top_products'))->pluck('name');
    expect($names->first())->toBe('صنف رائج');
    expect($names)->not->toContain('صنف مكدّس');
});
