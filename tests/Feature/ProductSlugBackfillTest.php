<?php

use App\Models\Product;
use App\Support\ProductIdentifiers;

test('backfill re-derives every slug from its name', function () {
    $keep = Product::create(['name_ar' => 'كباسة شطاف كروم', 'slug' => 'كباسة-شطاف-كروم', 'price' => 1]);
    $dated = Product::create(['name_ar' => 'حنفية برم نحاس 1/2 انش', 'slug' => 'حنفية-برم-نحاس12-انش', 'price' => 1]);
    $latin = Product::create(['name_ar' => 'PVC Elbow 1 1/4', 'slug' => 'pvczx-elbow', 'price' => 1]);

    $this->artisan('products:backfill-slugs')->assertExitCode(0);

    // A slug that already matches its name is left alone.
    expect($keep->fresh()->slug)->toBe('كباسة-شطاف-كروم');
    // A legacy slug collapsed around digits is normalised the same way the
    // catalogue does it today.
    expect($dated->fresh()->slug)->toBe('حنفية-برم-نحاس-1-2-انش');
    expect($latin->fresh()->slug)->toBe('pvc-elbow-1-1-4');
});

test('backfill keeps slugs unique across the table', function () {
    $first = Product::create(['name_ar' => 'صنبور', 'slug' => 'whatever-1', 'price' => 1]);
    $second = Product::create(['name_ar' => 'صنبور', 'slug' => 'whatever-2', 'price' => 1]);

    $this->artisan('products:backfill-slugs')->assertExitCode(0);

    expect($first->fresh()->slug)->toBe('صنبور');
    expect($second->fresh()->slug)->toBe('صنبور-2');
});

test('backfill dry-run reports changes without writing them', function () {
    $product = Product::create(['name_ar' => 'حنفية برم نحاس 1/2 انش', 'slug' => 'حنفية-برم-نحاس12-انش', 'price' => 1]);

    $this->artisan('products:backfill-slugs', ['--dry-run' => true])->assertExitCode(0);

    expect($product->fresh()->slug)->toBe('حنفية-برم-نحاس12-انش');
});

test('backfill with nothing to change exits cleanly', function () {
    Product::create([
        'name_ar' => 'كباسة شطاف كروم',
        'slug' => ProductIdentifiers::slugify('كباسة شطاف كروم'),
        'price' => 1,
    ]);

    $this->artisan('products:backfill-slugs')->assertExitCode(0);
});