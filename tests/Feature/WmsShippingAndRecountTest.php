<?php

use App\Models\Product;
use App\Models\ProductWarehouseAssignment;
use App\Models\ShippingManifest;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

/**
 * Routes the code diagnostics found pointing at missing actions, and the
 * assignment recount that only ever applied once.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->warehouse = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-WMS-T', 'is_active' => true]);
});

it('completes an in-transit manifest', function () {
    $manifest = ShippingManifest::create([
        'warehouse_id' => $this->warehouse->id, 'manifest_number' => 'SM-T-1', 'shipping_date' => now()->toDateString(),
        'status' => ShippingManifest::STATUS_IN_TRANSIT, 'total_packages' => 2,
    ]);
    $this->actingAs($this->user)->postJson("/api/v1/admin/wms/shipping-manifests/{$manifest->id}/complete")->assertOk();

    expect($manifest->refresh()->status)->toBe(ShippingManifest::STATUS_DELIVERED)
        ->and($manifest->actual_delivery)->not->toBeNull();

    // Completing twice is refused, not repeated.
    $this->postJson("/api/v1/admin/wms/shipping-manifests/{$manifest->id}/complete")->assertStatus(400);
});

it('reports shipping statistics and lists manifests', function () {
    ShippingManifest::create(['warehouse_id' => $this->warehouse->id, 'manifest_number' => 'SM-T-2', 'shipping_date' => now()->toDateString(), 'status' => 'pending', 'total_packages' => 3]);

    $this->actingAs($this->user)->getJson('/api/v1/admin/wms/shipping/statistics')
        ->assertOk()
        ->assertJsonPath('total_manifests', 1)
        ->assertJsonPath('total_packages', 3);

    $this->getJson('/api/v1/admin/wms/shipping-manifests')->assertOk();
});

it('applies every recount from the assignment screen, not only the first', function () {
    $product = Product::factory()->create(['cost_price' => 5]);
    app(InventoryService::class)->receive($product->id, 10, $this->warehouse->id, ['key' => 'wms-open', 'unit_cost' => 5]);
    $assignment = ProductWarehouseAssignment::create([
        'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'is_active' => true,
    ]);
    $onHand = fn () => (int) WarehouseInventory::where('product_id', $product->id)->where('warehouse_id', $this->warehouse->id)->value('quantity');

    $this->actingAs($this->user)->putJson("/api/v1/admin/wms/assignments/{$assignment->id}", ['quantity' => 7])->assertOk();
    expect($onHand())->toBe(7);

    $this->putJson("/api/v1/admin/wms/assignments/{$assignment->id}", ['quantity' => 12])->assertOk();
    expect($onHand())->toBe(12);

    // Resubmitting the same count changes nothing.
    $this->putJson("/api/v1/admin/wms/assignments/{$assignment->id}", ['quantity' => 12])->assertOk();
    expect($onHand())->toBe(12);
});
