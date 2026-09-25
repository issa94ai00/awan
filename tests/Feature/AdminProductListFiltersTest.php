<?php

use App\Models\Product;
use App\Models\User;

/**
 * The admin products list's filters and cards.
 *
 * "Not featured" and "out of stock" used to be sent as `false`, which the API
 * read as "no filter", so both options returned the whole catalogue.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->token = $this->admin->createToken('test-token')->plainTextToken;
    $this->list = fn (array $query = []) => $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/admin/products?' . http_build_query($query))
        ->assertOk();

    $this->plenty = Product::factory()->create(['stock_quantity' => 50, 'min_stock' => 0, 'is_featured' => true, 'is_active' => true]);
    $this->low = Product::factory()->create(['stock_quantity' => 3, 'min_stock' => 5, 'is_featured' => false, 'is_active' => true]);
    $this->empty = Product::factory()->create(['stock_quantity' => 0, 'is_featured' => false, 'is_active' => false]);
});

$ids = fn ($response) => collect($response->json('data'))->pluck('id')->sort()->values()->all();

test('featured=0 lists only products that are not featured', function () use ($ids) {
    expect($ids(($this->list)(['featured' => 0])))->toBe(collect([$this->low->id, $this->empty->id])->sort()->values()->all());
    expect($ids(($this->list)(['featured' => 1])))->toBe([$this->plenty->id]);
});

test('stock_level filters by the counted quantity', function () use ($ids) {
    expect($ids(($this->list)(['stock_level' => 'out'])))->toBe([$this->empty->id]);
    expect($ids(($this->list)(['stock_level' => 'low'])))->toBe([$this->low->id]);
    expect($ids(($this->list)(['stock_level' => 'available'])))->toBe(collect([$this->plenty->id, $this->low->id])->sort()->values()->all());
});

test('the list can be sorted by stock', function () {
    $rows = ($this->list)(['sort_by' => 'stock_quantity', 'sort_order' => 'asc'])->json('data');
    expect(collect($rows)->pluck('id')->all())->toBe([$this->empty->id, $this->low->id, $this->plenty->id]);
});

test('with_summary returns catalogue-wide counts regardless of filters', function () {
    $summary = ($this->list)(['with_summary' => 1, 'featured' => 1])->json('summary');

    expect($summary)->toMatchArray([
        'total' => 3,
        'active' => 2,
        'inactive' => 1,
        'featured' => 1,
        'out_of_stock' => 1,
        'low_stock' => 1,
    ]);
});
