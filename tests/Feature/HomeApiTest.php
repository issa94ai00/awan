<?php

use App\Models\Category;
use App\Models\Product;

beforeEach(function () {
    $this->section = Category::create(['name_ar' => 'عدد', 'name_en' => 'Tools', 'slug' => 'tools', 'is_active' => true]);
    $this->child = Category::create(['name_ar' => 'مناشير', 'name_en' => 'Saws', 'slug' => 'saws', 'parent_id' => $this->section->id, 'is_active' => true]);
    Category::create(['name_ar' => 'فارغ', 'name_en' => 'Empty', 'slug' => 'empty', 'is_active' => true]);

    $this->product = fn (string $sku, array $extra = []) => Product::create([
        'name_ar' => $sku, 'name_en' => $sku, 'sku' => $sku, 'slug' => strtolower($sku), 'price' => 10,
        'category_id' => $this->child->id, 'is_active' => true, 'in_stock' => true, ...$extra,
    ]);
});

it('lists only top-level sections that have products, with the catalogue size', function () {
    ($this->product)('H-1', ['image_main' => 'uploads/a.png']);

    $data = $this->getJson('/api/v1/home')->assertOk()->json('data');

    expect(array_column($data['categories'], 'slug'))->toBe(['tools'])
        ->and($data['categories'][0]['thumbnail'])->toContain('uploads/a.png')
        ->and($data['stats'])->toBe(['products' => 1, 'sections' => 1]);
});

it('shows featured products when there are some', function () {
    ($this->product)('H-F', ['is_featured' => true]);
    ($this->product)('H-P', ['image_main' => 'uploads/p.png']);

    $data = $this->getJson('/api/v1/home')->assertOk()->json('data');

    expect($data['products_source'])->toBe('featured')
        ->and(array_column($data['featured_products'], 'sku'))->toBe(['H-F']);
});

it('falls back to in-stock products with real photos when nothing is featured', function () {
    ($this->product)('H-REAL', ['image_main' => 'uploads/p.png']);
    ($this->product)('H-DRAWN', ['image_main' => 'images_items/generic/saw.svg']);
    ($this->product)('H-OUT', ['image_main' => 'uploads/q.png', 'in_stock' => false]);
    ($this->product)('H-BARE');

    $data = $this->getJson('/api/v1/home')->assertOk()->json('data');

    expect($data['products_source'])->toBe('picks')
        ->and(array_column($data['featured_products'], 'sku'))->toBe(['H-REAL']);
});
