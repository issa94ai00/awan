<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

/**
 * Manual print priority on the price-offer screen.
 *
 * The printed price list reads in sections: classifications in the order they
 * are arranged, and within a section whatever order the admin dragged the rows
 * into. `products.sort_order` already existed and was editable on the product
 * form, but nothing had ever sorted by it — the list came back newest-first,
 * which is meaningless to a reader holding the printed sheet.
 *
 * Priorities are per-classification, so two categories both numbering from 1
 * must not interleave.
 */
function orderedCategory(string $name, int $sortOrder): Category
{
    return Category::create([
        'name_ar' => $name,
        'name_en' => $name,
        'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
        'sort_order' => $sortOrder,
        'is_active' => true,
    ]);
}

function productIn(Category $category, string $name, int $sortOrder): Product
{
    return Product::factory()->create([
        'category_id' => $category->id,
        'name_ar' => $name,
        'name_en' => $name,
        'sort_order' => $sortOrder,
        'is_active' => true,
    ]);
}

beforeEach(function () {
    $this->actor = User::factory()->admin()->create();
});

it('reads classifications in their own order, then products by priority', function () {
    $showers = orderedCategory('Showers', 2);
    $mixers = orderedCategory('Mixers', 1);

    $rail = productIn($showers, 'Rail set', 1);
    $head = productIn($showers, 'Shower head', 2);
    $small = productIn($mixers, 'Mixer 1/2', 2);
    $large = productIn($mixers, 'Mixer 3/4', 1);

    $ids = collect(
        $this->actingAs($this->actor)
            ->getJson('/api/v1/admin/products?sort=catalogue&per_page=100')
            ->assertOk()
            ->json('data')
    )->pluck('id')->all();

    expect($ids)->toBe([$large->id, $small->id, $rail->id, $head->id]);
});

it('files products with no classification last', function () {
    $mixers = orderedCategory('Mixers', 1);
    $filed = productIn($mixers, 'Mixer 3/4', 1);
    $loose = Product::factory()->create(['category_id' => null, 'is_active' => true]);

    $ids = collect(
        $this->actingAs($this->actor)
            ->getJson('/api/v1/admin/products?sort=catalogue&per_page=100')
            ->assertOk()
            ->json('data')
    )->pluck('id')->all();

    expect($ids)->toBe([$filed->id, $loose->id]);
});

it('writes the priority a drag produced', function () {
    $mixers = orderedCategory('Mixers', 1);
    $first = productIn($mixers, 'Mixer 3/4', 1);
    $second = productIn($mixers, 'Mixer 1/2', 2);
    $third = productIn($mixers, 'Mixer 1/4', 3);

    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/products/reorder', [
            'category_id' => $mixers->id,
            'product_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.updated', 3);

    expect($third->fresh()->sort_order)->toBe(1)
        ->and($first->fresh()->sort_order)->toBe(2)
        ->and($second->fresh()->sort_order)->toBe(3);
});

it('ignores ids filed under another classification instead of failing the drag', function () {
    $mixers = orderedCategory('Mixers', 1);
    $showers = orderedCategory('Showers', 2);
    $mine = productIn($mixers, 'Mixer 3/4', 9);
    $theirs = productIn($showers, 'Rail set', 9);

    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/products/reorder', [
            'category_id' => $mixers->id,
            'product_ids' => [$theirs->id, $mine->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.updated', 1);

    // The stranger keeps its own priority, and numbering closes over the gap
    // rather than leaving the surviving product at position 2.
    expect($mine->fresh()->sort_order)->toBe(1)
        ->and($theirs->fresh()->sort_order)->toBe(9);
});

it('refuses a reorder with no products', function () {
    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/products/reorder', ['product_ids' => []])
        ->assertStatus(422);
});
