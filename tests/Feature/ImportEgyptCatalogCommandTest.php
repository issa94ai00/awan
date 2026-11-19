<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseInventory;

it('imports products, their variants and warehouse stock rows from the json file', function () {
    $relative = 'storage/framework/testing/egypt-catalog-'.uniqid().'.json';
    $path = base_path($relative);
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, json_encode([
        [
            'name_ar' => 'منتج اختبار',
            'name_en' => 'Test product',
            'sku' => 'EGY-TEST-1',
            'price' => 100,
            'variants' => [
                ['sku' => 'EGY-TEST-1-A', 'price' => 120, 'stock_quantity' => 7, 'size' => 'L'],
            ],
        ],
    ]));

    $this->artisan('egypt:import-catalog', ['--file' => $relative])->assertSuccessful();
    @unlink($path);

    $product = Product::where('name_ar', 'منتج اختبار')->first();
    expect($product)->not->toBeNull()
        ->and((float) $product->cost_price)->toBe(85.0);

    $variant = ProductVariant::where('product_id', $product->id)->first();
    expect($variant)->not->toBeNull()
        ->and((float) $variant->cost_price)->toBe(102.0)
        ->and((int) $variant->stock_quantity)->toBe(7);

    // Stock rows only exist when the install has a warehouse.
    if (\App\Models\Warehouse::count() > 0) {
        expect(WarehouseInventory::where('product_id', $product->id)->count())->toBe(2);
    }
});
