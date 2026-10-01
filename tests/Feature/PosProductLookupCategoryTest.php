<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * The sales order form narrows its product search to a category, or browses
 * a category with nothing typed. The filter used to be shown and never sent.
 */
beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->mixers = Category::create(['name_ar' => 'خلاطات', 'name_en' => 'Mixers', 'slug' => 'mixers', 'is_active' => true]);
    $this->sinks = Category::create(['name_ar' => 'مغاسل', 'name_en' => 'Sinks', 'slug' => 'sinks', 'is_active' => true]);

    Product::factory()->create(['name_ar' => 'خلاط مغسلة ذهبي', 'category_id' => $this->mixers->id, 'is_active' => true]);
    Product::factory()->create(['name_ar' => 'مغسلة خلاط مدمج', 'category_id' => $this->sinks->id, 'is_active' => true]);
});

$lookup = fn (array $params) => collect(test()->getJson(route('api.pos.products.lookup', $params))->assertOk()->json('data'))
    ->pluck('name_ar')->sort()->values()->all();

test('a category narrows the words searched for', function () use ($lookup) {
    expect($lookup(['q' => 'خلاط']))->toBe(['خلاط مغسلة ذهبي', 'مغسلة خلاط مدمج']);
    expect($lookup(['q' => 'خلاط', 'category_id' => $this->mixers->id, 'expand_variants' => 1]))->toBe(['خلاط مغسلة ذهبي']);
});

test('a category alone lists what is in it', function () use ($lookup) {
    expect($lookup(['category_id' => $this->sinks->id, 'expand_variants' => 1]))->toBe(['مغسلة خلاط مدمج']);
});
