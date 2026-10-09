<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;

/**
 * Products whose variants hold stock that was never received into a
 * warehouse get it as opening stock — reported first, moved only on --apply.
 */
beforeEach(function () {
    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي', 'code' => 'WH-OPEN', 'status' => 'active',
        'is_active' => true, 'is_primary' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->drain = Product::create(['name_ar' => 'جريدة تصريف', 'sku' => 'FD', 'price' => 5, 'cost_price' => 2]);
    ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-4', 'size' => '4"', 'price' => 1, 'cost_price' => 3, 'stock_quantity' => 30]);
    ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-5', 'size' => '5"', 'price' => 2, 'stock_quantity' => 10]);

    // Per-pack pricing and no cost anywhere: reported, still received.
    $this->blades = Product::create(['name_ar' => 'شفرة مشرط / سعر المئة', 'sku' => 'BL', 'price' => 2]);
    ProductVariant::create(['product_id' => $this->blades->id, 'sku' => 'BL-1', 'size' => 'عادية', 'price' => 2, 'stock_quantity' => 6000]);

    // Already holds more than its variants: left alone.
    $this->tap = Product::create(['name_ar' => 'حنفية', 'sku' => 'TAP', 'price' => 20, 'cost_price' => 10]);
    ProductVariant::create(['product_id' => $this->tap->id, 'sku' => 'TAP-1', 'size' => 'S', 'price' => 20, 'stock_quantity' => 2]);
    app(InventoryService::class)->receive($this->tap->id, 5, $this->warehouse->id, ['key' => 'tap-seed', 'unit_cost' => 10]);

    $this->report = sys_get_temp_dir().'/variant-opening-'.uniqid().'.csv';
});

afterEach(fn () => @unlink($this->report));

test('without --apply it only writes the report', function () {
    $this->artisan('inventory:variant-opening-stock', ['--report' => $this->report])->assertSuccessful();

    expect((int) $this->drain->refresh()->stock_quantity)->toBe(0);

    $csv = array_map('str_getcsv', file($this->report));
    $byId = collect(array_slice($csv, 1))->keyBy(0);

    expect($byId[$this->drain->id][7])->toBe('40')           // to_add
        ->and((float) $byId[$this->drain->id][8])->toBe(2.75)  // (30×3 + 10×2) / 40
        ->and($byId[$this->blades->id][10])->toContain('no_cost')
        ->and($byId[$this->blades->id][10])->toContain('pack_priced')
        ->and($byId[$this->blades->id][10])->toContain('large_quantity')
        ->and($byId[$this->tap->id][7])->toBe('0')
        ->and($byId[$this->tap->id][10])->toContain('warehouse_above_variants');
});

test('--apply receives the difference as opening stock, once', function () {
    $this->artisan('inventory:variant-opening-stock', ['--apply' => true, '--report' => $this->report])->assertSuccessful();
    $this->artisan('inventory:variant-opening-stock', ['--apply' => true, '--report' => $this->report])->assertSuccessful();

    expect((int) $this->drain->refresh()->stock_quantity)->toBe(40)
        ->and((int) $this->blades->refresh()->stock_quantity)->toBe(6000)
        ->and((int) $this->tap->refresh()->stock_quantity)->toBe(5);

    $movement = StockMovement::where('product_id', $this->drain->id)->where('movement_key', 'variant-opening:product:'.$this->drain->id)->first();
    expect($movement->warehouse_id)->toBe($this->warehouse->id)
        ->and((float) $movement->unit_cost)->toBe(2.75);
});

test('--only and --exclude pick the products', function () {
    $this->artisan('inventory:variant-opening-stock', [
        '--apply' => true, '--only' => $this->drain->id.','.$this->blades->id, '--exclude' => (string) $this->blades->id,
        '--report' => $this->report,
    ])->assertSuccessful();

    expect((int) $this->drain->refresh()->stock_quantity)->toBe(40)
        ->and((int) $this->blades->refresh()->stock_quantity)->toBe(0);
});

test('--estimate-cost values a variant with no cost from its price and its category\'s margins', function () {
    $sanitary = \App\Models\Category::create(['name_ar' => 'صحية', 'name_en' => 'Sanitary', 'slug' => 'sanitary', 'is_active' => 1]);
    // Five costed products in the category at cost = 0.4 × price, and one
    // absurd ratio that must not pull the median.
    foreach ([10, 20, 30, 40, 50] as $price) {
        Product::create(['name_ar' => 'مرجع '.$price, 'price' => $price, 'cost_price' => $price * 0.4, 'category_id' => $sanitary->id]);
    }
    Product::create(['name_ar' => 'خطأ إدخال', 'price' => 1, 'cost_price' => 13, 'category_id' => $sanitary->id]);

    $mixer = Product::create(['name_ar' => 'خلاط', 'sku' => 'MX', 'price' => 50, 'category_id' => $sanitary->id]);
    ProductVariant::create(['product_id' => $mixer->id, 'sku' => 'MX-1', 'size' => 'S', 'price' => 20, 'stock_quantity' => 10]);
    ProductVariant::create(['product_id' => $mixer->id, 'sku' => 'MX-2', 'size' => 'L', 'price' => 0, 'stock_quantity' => 10]);

    $this->artisan('inventory:variant-opening-stock', ['--estimate-cost' => true, '--apply' => true, '--report' => $this->report])->assertSuccessful();

    $csv = collect(array_slice(array_map('str_getcsv', file($this->report)), 1))->keyBy(0);

    // S: 20 × 0.4 = 8. L has no price: it is priced like its sibling (20),
    // not like the product (50), which is often a box price or a typo.
    expect((float) $csv[$mixer->id][8])->toBe(8.0)
        ->and($csv[$mixer->id][10])->toContain('estimated_cost')
        ->and($csv[$mixer->id][10])->toContain('unpriced_variant');

    // A real cost is never replaced: the drain's 5" has none of its own but
    // the drain does, so nothing on it is estimated.
    expect((float) $csv[$this->drain->id][8])->toBe(2.75)
        ->and($csv[$this->drain->id][10])->not->toContain('estimated');

    // Per hundred: 2 per hundred is 0.02 a piece, times the catalogue median.
    $blades = (float) $csv[$this->blades->id][8];
    expect($blades)->toBeGreaterThan(0)->toBeLessThan(0.02)
        ->and($csv[$this->blades->id][10])->not->toContain('no_cost');

    // The estimate goes on the stock, never into the catalogue.
    expect(ProductVariant::where('sku', 'MX-1')->value('cost_price'))->toBeNull();
    expect((float) StockMovement::where('movement_key', 'variant-opening:product:'.$mixer->id)->value('unit_cost'))->toBe(8.0);
});

test('--cost-ratio fixes one ratio for every estimate', function () {
    $this->artisan('inventory:variant-opening-stock', ['--estimate-cost' => true, '--cost-ratio' => '0.5', '--report' => $this->report])->assertSuccessful();

    $csv = collect(array_slice(array_map('str_getcsv', file($this->report)), 1))->keyBy(0);

    // 2 per hundred → 0.02 a piece × 0.5.
    expect((float) $csv[$this->blades->id][8])->toBe(0.01)
        ->and($csv[$this->blades->id][10])->toContain('estimated_cost');
});
