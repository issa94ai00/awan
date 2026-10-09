<?php

use App\Models\Product;
use App\Support\SearchTerms;

/**
 * Search matches every word of the query wherever it sits: "خلاط ذهبي"
 * used to find nothing for "خلاط مغسلة لون ذهبي" because it looked for the
 * phrase as typed.
 */
beforeEach(function () {
    $this->gold = Product::factory()->create(['name_ar' => 'خلاط مغسلة لون ذهبي', 'name_en' => 'Basin mixer gold', 'brand' => 'Grohe']);
    $this->chrome = Product::factory()->create(['name_ar' => 'خلاط مغسلة كروم', 'name_en' => 'Basin mixer chrome', 'brand' => 'Ideal']);
});

$names = fn ($query) => $query->pluck('name_ar')->sort()->values()->all();

test('words that are apart or out of order still match', function () use ($names) {
    expect($names(Product::whereSearch(['name_ar'], 'خلاط ذهبي')))->toBe(['خلاط مغسلة لون ذهبي']);
    expect($names(Product::whereSearch(['name_ar'], 'ذهبي   خلاط')))->toBe(['خلاط مغسلة لون ذهبي']);
});

test('each word may match a different column', function () use ($names) {
    expect($names(Product::whereSearch(['name_ar', 'brand'], 'خلاط grohe')))->toBe(['خلاط مغسلة لون ذهبي']);
});

test('every word has to match somewhere', function () use ($names) {
    expect($names(Product::whereSearch(['name_ar', 'name_en'], 'خلاط برونز')))->toBe([]);
});

test('an empty search filters nothing', function () {
    expect(Product::whereSearch(['name_ar'], '  ')->count())->toBe(2);
});

test('words are split on whitespace and deduplicated', function () {
    expect(SearchTerms::words("  خلاط \t ذهبي خلاط "))->toBe(['خلاط', 'ذهبي']);
});

test('% and _ in a search are taken literally', function () use ($names) {
    Product::factory()->create(['name_ar' => 'خصم 100% كامل']);

    expect($names(Product::whereSearch(['name_ar'], '100%')))->toBe(['خصم 100% كامل']);
    expect(Product::whereSearch(['name_ar'], '%')->count())->toBe(1);
    expect(Product::whereSearch(['name_ar'], '_')->count())->toBe(0);
});

test('a relation column only matches the related row', function () {
    $category = \App\Models\Category::create(['name_ar' => 'خلاطات', 'name_en' => 'Mixers', 'slug' => 'mixers', 'is_active' => true]);
    $this->gold->update(['category_id' => $category->id]);

    expect(Product::whereSearch(['category.name_ar'], 'خلاطات')->pluck('id')->all())->toBe([$this->gold->id]);
});
