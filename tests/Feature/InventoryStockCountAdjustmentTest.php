<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

/**
 * The inventory screen records a stock count as a signed adjustment: the
 * counted figure minus the book figure. The endpoint used to demand a positive
 * quantity for every movement type, so a count that came up short could not be
 * recorded at all.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع الجرد', 'code' => 'WH-COUNT', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'خلاط', 'sku' => 'SKU-COUNT', 'price' => 40, 'cost_price' => 15,
    ]);

    app(InventoryService::class)->receive(
        $this->product->id, 20, $this->warehouse->id, ['key' => 'open:'.uniqid()]
    );
});

$onHand = fn ($test) => (float) WarehouseInventory::where('product_id', $test->product->id)
    ->where('warehouse_id', $test->warehouse->id)->value('quantity');

test('a stock count that finds fewer units takes them off', function () use ($onHand) {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/inventory/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => 'adjustment',
            'quantity' => -5,
        ])
        ->assertCreated();

    expect($onHand($this))->toBe(15.0);
});

test('an adjustment of zero is refused', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/inventory/movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => 'adjustment',
            'quantity' => 0,
        ])
        ->assertUnprocessable();
});

test('receipts and issues still need a positive whole quantity', function () use ($onHand) {
    foreach ([['in', -3], ['out', -3], ['in', 0.5]] as [$type, $quantity]) {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/inventory/movements', [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'movement_type' => $type,
                'quantity' => $quantity,
            ])
            ->assertUnprocessable();
    }

    expect($onHand($this))->toBe(20.0);
});
