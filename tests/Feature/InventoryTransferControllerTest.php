<?php

use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

/**
 * The back office's stock transfers: raised against the source's stock, shipped,
 * received at the destination, or cancelled while the goods are still held.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();

    $this->source = Warehouse::create(['name' => 'المستودع الرئيسي', 'code' => 'WH-MAIN', 'is_active' => true, 'is_primary' => true]);
    $this->branch = Warehouse::create(['name' => 'فرع حلب', 'code' => 'WH-ALP', 'is_active' => true]);

    $this->product = Product::factory()->create(['name_ar' => 'خلاط مغسلة', 'cost_price' => 7, 'unit' => 'قطعة']);
    $this->other = Product::factory()->create(['name_ar' => 'سيفون', 'cost_price' => 3, 'unit' => 'قطعة']);

    app(InventoryService::class)->receive($this->product->id, 50, $this->source->id, ['key' => 'trf-opening-1', 'unit_cost' => 7]);
    app(InventoryService::class)->receive($this->other->id, 5, $this->source->id, ['key' => 'trf-opening-2', 'unit_cost' => 3]);

    $this->stock = fn (Warehouse $warehouse, Product $product, string $column = 'quantity') => (int) WarehouseInventory::where('warehouse_id', $warehouse->id)
        ->where('product_id', $product->id)->sum($column);
});

it('raises a transfer that holds the stock and costs lines from the catalogue', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/inventory/transfers', [
            'from_warehouse_id' => $this->source->id,
            'to_warehouse_id' => $this->branch->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 999]],
        ])
        ->assertCreated();

    $transfer = InventoryTransfer::with('items')->sole();

    expect($transfer->status)->toBe(InventoryTransfer::STATUS_PENDING)
        ->and($transfer->transfer_number)->toStartWith('TRF-')
        ->and((float) $transfer->items->first()->unit_cost)->toBe(7.0)
        ->and(($this->stock)($this->source, $this->product, 'reserved_quantity'))->toBe(10);
});

it('reports every short line at once and holds nothing', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/inventory/transfers', [
            'from_warehouse_id' => $this->source->id,
            'to_warehouse_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 80],
                ['product_id' => $this->other->id, 'quantity' => 9],
            ],
        ])
        ->assertStatus(422);

    expect($response->json('data.shortfalls'))->toHaveCount(2)
        ->and($response->json('message'))->toContain('خلاط مغسلة')->toContain('سيفون')
        ->and(InventoryTransfer::count())->toBe(0)
        ->and(($this->stock)($this->source, $this->product, 'reserved_quantity'))->toBe(0);
});

it('ships and receives a transfer, moving the stock to the destination', function () {
    $this->actingAs($this->user)->postJson('/api/v1/inventory/transfers', [
        'from_warehouse_id' => $this->source->id,
        'to_warehouse_id' => $this->branch->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 10]],
    ])->assertCreated();
    $transfer = InventoryTransfer::with('items')->sole();

    $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/ship")->assertOk();
    expect($transfer->refresh()->status)->toBe(InventoryTransfer::STATUS_IN_TRANSIT)
        ->and(($this->stock)($this->source, $this->product))->toBe(40)
        ->and(($this->stock)($this->source, $this->product, 'reserved_quantity'))->toBe(0);

    // Two short on arrival: booked as received, reported as a discrepancy.
    $response = $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/receive", [
        'items' => [['transfer_item_id' => $transfer->items->first()->id, 'quantity_received' => 8]],
    ])->assertOk();

    expect($transfer->refresh()->status)->toBe(InventoryTransfer::STATUS_COMPLETED)
        ->and(($this->stock)($this->branch, $this->product))->toBe(8)
        ->and($response->json('effects.discrepancies.0.missing'))->toBe(2);
});

it('cancels a pending transfer and puts the held stock back on sale', function () {
    $this->actingAs($this->user)->postJson('/api/v1/inventory/transfers', [
        'from_warehouse_id' => $this->source->id,
        'to_warehouse_id' => $this->branch->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 10]],
    ])->assertCreated();
    $transfer = InventoryTransfer::sole();

    $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/cancel", ['reason' => 'طلب مكرر'])->assertOk();

    expect($transfer->refresh()->status)->toBe(InventoryTransfer::STATUS_CANCELLED)
        ->and($transfer->notes)->toContain('طلب مكرر')
        ->and(($this->stock)($this->source, $this->product, 'reserved_quantity'))->toBe(0);
});

it('lists transfers by search and warehouse, with a count per status', function () {
    foreach ([3, 4] as $quantity) {
        $this->actingAs($this->user)->postJson('/api/v1/inventory/transfers', [
            'from_warehouse_id' => $this->source->id,
            'to_warehouse_id' => $this->branch->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]],
        ])->assertCreated();
    }
    $this->postJson('/api/v1/inventory/transfers/'.InventoryTransfer::min('id').'/ship')->assertOk();

    $response = $this->getJson('/api/v1/inventory/transfers?warehouse_id='.$this->branch->id.'&search=خلاط&status=pending')
        ->assertOk();

    expect($response->json('data.total'))->toBe(1)
        ->and($response->json('status_counts.pending'))->toBe(1)
        ->and($response->json('status_counts.in_transit'))->toBe(1);

    // An empty filter is no filter.
    expect($this->getJson('/api/v1/inventory/transfers?status=')->json('data.total'))->toBe(2);
});

it('lets a sales rep look at transfers but not move stock', function () {
    $rep = User::factory()->operator()->create(['is_admin' => false]);
    $rep->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'sells', 'guard_name' => 'web'])->id]);
    $rep = $rep->fresh();
    expect($rep->isSalesScoped())->toBeTrue();

    $this->actingAs($rep)->getJson('/api/v1/inventory/transfers')->assertOk();
    $this->postJson('/api/v1/inventory/transfers', [
        'from_warehouse_id' => $this->source->id,
        'to_warehouse_id' => $this->branch->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
    ])->assertForbidden();

    expect(InventoryTransfer::count())->toBe(0);
});
