<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\Inventory\InventoryService;

/**
 * Stock Balances & Products had three separate logic bugs before this:
 * getStockTransactions was a stub that always returned an empty ledger no
 * matter how much stock had actually moved; suggestStockLevels fabricated a
 * "smart" suggestion from a hardcoded constant instead of real consumption;
 * and indexProducts loaded the entire catalog (with every warehouse's
 * inventory row) on every request. Pins all three down, plus the bin
 * code/bin_code split that left newly created bins showing a blank code on
 * Picking/Packing/Cycle Count screens.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع المخزون', 'code' => 'WH-STOCK', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'صنبور مطبخ', 'sku' => 'SKU-STOCK', 'price' => 60, 'cost_price' => 25,
    ]);
});

test('the stock transaction ledger reflects real movements, not an empty stub', function () {
    $inventory = app(InventoryService::class);
    $inventory->receive($this->product->id, 40, $this->warehouse->id, ['key' => uniqid()]);
    $inventory->issue($this->product->id, 15, $this->warehouse->id, ['key' => uniqid(), 'reference' => 'SO-1']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/stock/transactions?'.http_build_query([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]))
        ->assertOk();

    $rows = $response->json('data');
    expect($rows)->toHaveCount(2);
    expect(collect($rows)->pluck('movement_type')->all())->toContain('in', 'out');
    $out = collect($rows)->firstWhere('movement_type', 'out');
    expect($out['reference_document'])->toBe('SO-1');
});

test('suggested stock levels come from real consumption, not a fixed constant', function () {
    $inventory = app(InventoryService::class);
    $inventory->receive($this->product->id, 500, $this->warehouse->id, ['key' => uniqid()]);
    // 90 units issued "recently" (within the 90-day lookback) — the
    // suggestion is expected to scale with this, not with a hardcoded 10.
    $inventory->issue($this->product->id, 90, $this->warehouse->id, ['key' => uniqid()]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/suggest-stock-levels?'.http_build_query([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'lead_time_days' => 10,
        ]))
        ->assertOk();

    // avg daily consumption = 90 / 90 = 1/day.
    expect((float) $response->json('avg_daily_consumption'))->toBe(1.0);
    expect($response->json('total_consumed'))->toBe(90);
    expect($response->json('min_stock'))->toBeGreaterThan(0);
    expect($response->json('max_stock'))->toBeGreaterThan($response->json('min_stock'));
});

test('a product with no sales history gets an honest zero suggestion, not a fabricated one', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/suggest-stock-levels?'.http_build_query([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]))
        ->assertOk();

    expect((float) $response->json('avg_daily_consumption'))->toBe(0.0);
    expect($response->json('total_consumed'))->toBe(0);
    expect($response->json('safety_stock'))->toBe(0);
});

test('the product list is paginated and caps an oversized per_page', function () {
    Product::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/products?per_page=2')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('per_page'))->toBe(2);
    expect($response->json('total'))->toBeGreaterThanOrEqual(4);
});

test('a bin created through the API is readable by bin_code on Picking/Packing/Cycle Count screens', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/wms/bins', [
            'warehouse_id' => $this->warehouse->id,
            'code' => 'BIN-A1',
            'name' => 'Bin A1',
            'type' => 'storage',
            'capacity_type' => 'count',
        ])
        ->assertCreated();

    $bin = WarehouseBin::find($response->json('id'));
    expect($bin->code)->toBe('BIN-A1');
    expect($bin->bin_code)->toBe('BIN-A1');

    $this->actingAs($this->admin)
        ->putJson("/api/v1/wms/bins/{$bin->id}", ['code' => 'BIN-A2'])
        ->assertOk();

    $bin->refresh();
    expect($bin->code)->toBe('BIN-A2');
    expect($bin->bin_code)->toBe('BIN-A2');
});
