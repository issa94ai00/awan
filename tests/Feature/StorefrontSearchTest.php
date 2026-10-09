<?php

use App\Models\Category;
use App\Models\Product;

beforeEach(function () {
    $category = Category::create(['name_ar' => 'مفكات', 'name_en' => 'Screwdrivers', 'slug' => 'screwdrivers', 'is_active' => true]);

    foreach (range(1, 15) as $n) {
        Product::create([
            'name_ar' => "لقمة مفك {$n}", 'name_en' => "Screwdriver bit {$n}", 'sku' => "SRCH-{$n}", 'slug' => "srch-{$n}",
            'price' => $n, 'category_id' => $category->id, 'is_active' => true, 'in_stock' => $n % 2 === 0,
        ]);
    }
    Product::create([
        'name_ar' => 'مفك كهربائي', 'name_en' => 'Electric screwdriver', 'sku' => 'DRV-1', 'slug' => 'drv-1',
        'price' => 90, 'category_id' => $category->id, 'is_active' => true, 'in_stock' => true,
    ]);
});

it('pages through every match and says how many there are', function () {
    $first = $this->getJson('/api/v1/search?q='.urlencode('مفك'))->assertOk()->json('data');

    expect($first['pagination'])->toMatchArray(['total' => 16, 'last_page' => 2, 'per_page' => 12])
        ->and($first['products'])->toHaveCount(12);

    $second = $this->getJson('/api/v1/search?page=2&q='.urlencode('مفك'))->json('data');
    expect($second['products'])->toHaveCount(4)
        ->and(array_intersect(array_column($first['products'], 'sku'), array_column($second['products'], 'sku')))->toBe([]);
});

it('ranks names that start with the query first, and finds a product by its code', function () {
    $data = $this->getJson('/api/v1/search?q='.urlencode('مفك'))->json('data');
    expect($data['products'][0]['sku'])->toBe('DRV-1');

    $bySku = $this->getJson('/api/v1/search?q=DRV')->json('data');
    expect(array_column($bySku['products'], 'sku'))->toBe(['DRV-1']);
});

it('sorts and filters search results the way category pages do', function () {
    $cheapest = $this->getJson('/api/v1/search?sort=price_asc&q='.urlencode('مفك'))->json('data.products.0.sku');
    expect($cheapest)->toBe('SRCH-1');

    $inStock = $this->getJson('/api/v1/search?in_stock=1&q='.urlencode('مفك'))->json('data.pagination.total');
    expect($inStock)->toBe(8);
});

it('treats a single Arabic letter as too short to search', function () {
    $this->getJson('/api/v1/search?q='.urlencode('م'))
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 0);
});
