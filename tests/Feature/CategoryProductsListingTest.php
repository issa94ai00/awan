<?php

use App\Models\Category;
use App\Models\Product;

beforeEach(function () {
    $this->section = Category::create(['name_ar' => 'عدد', 'name_en' => 'Tools', 'slug' => 'tools', 'is_active' => true]);
    $this->saws = Category::create(['name_ar' => 'مناشير', 'name_en' => 'Saws', 'slug' => 'saws', 'parent_id' => $this->section->id, 'is_active' => true]);
    $this->empty = Category::create(['name_ar' => 'فارغ', 'name_en' => 'Empty', 'slug' => 'empty', 'parent_id' => $this->section->id, 'is_active' => true]);

    $product = fn (string $sku, ?float $price, string $name, bool $inStock = true, ?Category $category = null) => Product::create([
        'name_ar' => $name, 'name_en' => $name, 'sku' => $sku, 'slug' => strtolower($sku), 'price' => $price,
        'category_id' => ($category ?? $this->saws)->id, 'is_active' => true, 'in_stock' => $inStock,
    ]);

    $product('LST-B', 30, 'B saw');
    $product('LST-A', 10, 'A saw', false);
    $product('LST-C', null, 'C saw');          // price on request
    $product('LST-D', 20, 'D drill', true, $this->section);
});

$listing = fn (string $query = '') => test()->getJson('/api/v1/categories/tools/products?expand_variants=0'.$query)->assertOk()->json('data');

it('sorts by price with unpriced products last, both ways', function () use ($listing) {
    expect(array_column($listing('&sort=price_asc')['products'], 'sku'))->toBe(['LST-A', 'LST-D', 'LST-B', 'LST-C'])
        ->and(array_column($listing('&sort=price_desc')['products'], 'sku'))->toBe(['LST-B', 'LST-D', 'LST-A', 'LST-C']);
});

it('sorts by name in the language asked for', function () use ($listing) {
    expect(array_column($listing('&sort=name&lang=en')['products'], 'sku'))->toBe(['LST-A', 'LST-B', 'LST-C', 'LST-D']);
});

it('can list only what is in stock', function () use ($listing) {
    $data = $listing('&in_stock=1');

    expect($data['pagination']['total'])->toBe(3)
        ->and(array_column($data['products'], 'sku'))->not->toContain('LST-A');
});

it('offers the subcategories that have products, and the way back up', function () use ($listing) {
    $data = $listing();

    expect($data['subcategories'])->toHaveCount(1)
        ->and($data['subcategories'][0])->toMatchArray(['slug' => 'saws', 'product_count' => 3])
        ->and($data['parent'])->toBeNull();

    $child = test()->getJson('/api/v1/categories/saws/products')->assertOk()->json('data');
    expect($child['parent'])->toMatchArray(['slug' => 'tools'])
        ->and($child['subcategories'])->toBe([]);
});

// /products uses the general endpoint; it must read the same as a category page.
$all = fn (string $query = '') => test()->getJson('/api/v1/products?expand_variants=0&category_slug=tools'.$query)->assertOk()->json();

it('sorts the all-products listing the way a category page does', function () use ($all) {
    expect(array_column($all('&sort=price_asc')['data'], 'sku'))->toBe(['LST-A', 'LST-D', 'LST-B', 'LST-C'])
        ->and(array_column($all('&sort=name&lang=en')['data'], 'sku'))->toBe(['LST-A', 'LST-B', 'LST-C', 'LST-D'])
        ->and($all('&in_stock=1')['pagination']['total'])->toBe(3);
});
