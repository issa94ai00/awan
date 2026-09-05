<?php

use App\Models\Product;

test('backfill hands a SKU to every product that has none', function () {
    $without = Product::create(['name_ar' => 'تعتبي', 'slug' => 'no-sku-1', 'price' => 1]);
    $legacy = Product::create(['name_ar' => 'قديم', 'slug' => 'legacy', 'price' => 1, 'sku' => 'L182']);

    $this->artisan('products:backfill-skus')->assertExitCode(0);

    // Starts from the top of the series; the legacy L182 code is not in it.
    expect($without->fresh()->sku)->toBe('SKU-00001');
    // An existing SKU, legacy or not, is left untouched.
    expect($legacy->fresh()->sku)->toBe('L182');
});

test('backfill continues beyond codes already handed out', function () {
    Product::create(['name_ar' => 'أول', 'slug' => 's1', 'price' => 1, 'sku' => 'SKU-00003']);
    Product::create(['name_ar' => 'ثاني', 'slug' => 's2', 'price' => 1]);
    Product::create(['name_ar' => 'ثالث', 'slug' => 's3', 'price' => 1]);

    $this->artisan('products:backfill-skus')->assertExitCode(0);

    // Like nextSku(), the series continues past the highest existing code
    // rather than backfilling the gaps below it.
    expect(Product::find(2)->fresh()->sku)->toBe('SKU-00004');
    expect(Product::find(3)->fresh()->sku)->toBe('SKU-00005');
});

test('backfill skips an empty-string sku as if it were missing', function () {
    Product::create(['name_ar' => 'أول', 'slug' => 'e1', 'price' => 1, 'sku' => 'SKU-00005']);
    $blank = Product::create(['name_ar' => 'ثاني', 'slug' => 'e2', 'price' => 1, 'sku' => '']);

    $this->artisan('products:backfill-skus')->assertExitCode(0);

    // An empty sting is treated as missing and given the next free code.
    expect($blank->fresh()->sku)->toBe('SKU-00006');
});

test('backfill dry-run reports what would be issued without writing', function () {
    $without = Product::create(['name_ar' => 'تعتبي', 'slug' => 'no-sku-2', 'price' => 1, 'sku' => null]);

    $this->artisan('products:backfill-skus', ['--dry-run' => true])->assertExitCode(0);

    expect($without->fresh()->sku)->toBeNull();
});

test('backfill with every product already carrying a sku exits cleanly', function () {
    Product::create(['name_ar' => 'تعتبي', 'slug' => 'has-sku', 'price' => 1, 'sku' => 'SKU-00001']);

    $this->artisan('products:backfill-skus')->assertExitCode(0);
});