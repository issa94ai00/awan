<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

/**
 * One answer to "how many can I sell?".
 *
 * A warehouse row splits its units into condition buckets that add up to
 * `quantity`, and holds part of the sound bucket back for confirmed orders:
 *
 *     quantity = available_quantity + damaged_quantity + quarantined_quantity
 *     sellable = available_quantity - reserved_quantity
 *
 * The system had four different formulas for that second line, and they only
 * agreed while nothing was reserved, damaged or quarantined — which is to say,
 * until the day it mattered. These tests hold every reader to the one figure the
 * sell gate actually enforces.
 */
beforeEach(function () {
    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'كلادينج',
        'sku' => 'SKU-CLAD',
        'price' => 100,
        'cost_price' => 40,
    ]);

    $this->user = User::factory()->create();
    $this->inventory = app(InventoryService::class);

    // 100 on the shelf: 10 damaged, 5 quarantined, 85 sound — of which 20 are
    // already promised to a confirmed order. 65 may actually be sold.
    $this->row = WarehouseInventory::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'available_quantity' => 85,
        'damaged_quantity' => 10,
        'quarantined_quantity' => 5,
        'reserved_quantity' => 20,
        'reorder_point' => 30,
    ]);
});

test('availability is the sound units less what is already promised', function () {
    expect($this->row->available_stock)->toBe(65);
});

test('the SQL and the PHP form of the definition give the same number', function () {
    $viaSql = (int) WarehouseInventory::query()
        ->whereKey($this->row->id)
        ->withAvailable()
        ->value('available');

    expect($viaSql)->toBe($this->row->available_stock);
});

test('the sell gate lets exactly the available quantity out and no more', function () {
    // One more than available is refused...
    expect(fn () => $this->inventory->issue($this->product->id, 66, $this->warehouse->id))
        ->toThrow(RuntimeException::class);

    // ...and the figure named in the refusal is the same 65 the screen shows.
    expect($this->inventory->sellableQuantity($this->product->id, $this->warehouse->id))
        ->toBe($this->row->available_stock);
});

test('a negative balance reports as nothing available rather than as a debt', function () {
    // A repair case: more reserved than sound units left.
    $this->row->update(['available_quantity' => 5, 'reserved_quantity' => 20]);

    expect($this->row->refresh()->available_stock)->toBe(0);

    $viaSql = (int) WarehouseInventory::query()
        ->whereKey($this->row->id)
        ->withAvailable()
        ->value('available');

    expect($viaSql)->toBe(0);
});

test('holding stock moves nothing between the condition buckets', function () {
    $this->row->update(['available_quantity' => 85, 'reserved_quantity' => 0]);

    $this->inventory->reserve($this->product->id, 20, $this->warehouse->id);
    $this->row->refresh();

    // Reserved units are still sound and still on the shelf — only spoken for.
    expect($this->row->quantity)->toBe(100);
    expect($this->row->available_quantity)->toBe(85);
    expect($this->row->reserved_quantity)->toBe(20);

    // So exactly 20 leave availability, not 40.
    expect($this->row->available_stock)->toBe(65);

    // And the buckets still account for every unit on the shelf.
    expect($this->row->available_quantity + $this->row->damaged_quantity + $this->row->quarantined_quantity)
        ->toBe($this->row->quantity);
});

test('the stock screen reports the same figure as the sell gate', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/admin/inventory/stock')
        ->assertOk();

    $row = collect($response->json('data.stock'))->firstWhere('id', $this->row->id);

    expect((int) $row['available'])->toBe(65);
    // The gross figures ride along, so a balance held down by a reservation can
    // be told apart from one that is genuinely empty.
    expect((int) $row['quantity'])->toBe(100);
    expect((int) $row['reserved_quantity'])->toBe(20);
});

test('the summary totals and the valuation use the available figure', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/admin/inventory/summary')
        ->assertOk();

    expect((int) $response->json('data.summary.total_available'))->toBe(65);
    expect((int) $response->json('data.summary.total_quantity'))->toBe(100);
    // Valued at what can be sold, at cost: 65 x 40.
    expect((float) $response->json('data.summary.total_value'))->toBe(2600.0);

    $warehouse = collect($response->json('data.warehouses'))->firstWhere('id', $this->warehouse->id);
    expect((int) $warehouse['total_available'])->toBe(65);
    expect((int) $warehouse['total_quantity'])->toBe(100);
});

test('a row is low when its available figure is at the reorder point, not its gross one', function () {
    // 100 on the shelf against a reorder point of 30 looks comfortable; only 25
    // of them can be sold.
    $this->row->update(['available_quantity' => 45, 'reserved_quantity' => 20]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/admin/inventory/stock?status=low')
        ->assertOk();

    expect(collect($response->json('data.stock'))->pluck('id'))->toContain($this->row->id);

    expect(WarehouseInventory::lowStock()->pluck('id'))->toContain($this->row->id);
});

test('the متاح filter excludes rows that are out or low', function () {
    $healthy = WarehouseInventory::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => Product::create([
            'name_ar' => 'خلاط',
            'sku' => 'SKU-MIX',
            'price' => 50,
            'cost_price' => 20,
        ])->id,
        'quantity' => 90,
        'available_quantity' => 90,
        'damaged_quantity' => 0,
        'quarantined_quantity' => 0,
        'reserved_quantity' => 0,
        'reorder_point' => 10,
    ]);

    $empty = WarehouseInventory::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => Product::create([
            'name_ar' => 'مضخة',
            'sku' => 'SKU-PMP',
            'price' => 70,
            'cost_price' => 30,
        ])->id,
        'quantity' => 8,
        'available_quantity' => 8,
        'damaged_quantity' => 0,
        'quarantined_quantity' => 0,
        'reserved_quantity' => 8,
        'reorder_point' => 5,
    ]);

    // Plenty on the shelf, but only 5 of it sellable against a reorder point of
    // 30 — comfortable by the gross count, low by the one that matters.
    $this->row->update(['available_quantity' => 25, 'reserved_quantity' => 20]);

    $ids = collect(
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/inventory/stock?status=ok')
            ->assertOk()
            ->json('data.stock')
    )->pluck('id');

    // The filter used to fall through to `default => null` and return the lot.
    expect($ids)->toContain($healthy->id);
    expect($ids)->not->toContain($empty->id);
    expect($ids)->not->toContain($this->row->id);
});
