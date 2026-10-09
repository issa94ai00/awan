<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * Each variant keeps its own ordered list of details ("Power: 750W"), edited
 * on the price list and shown on the storefront for the size picked.
 */
beforeEach(function () {
    $this->actor = User::factory()->admin()->create();
    $this->product = Product::factory()->create(['is_active' => 1, 'slug' => 'grinder']);
});

it('stores a new variant with its details, cleaned and in order', function () {
    $response = $this->actingAs($this->actor)->postJson('/api/v1/admin/product-variants', [
        'product_id' => $this->product->id,
        'sku' => 'GR-750',
        'size' => '750W',
        'price' => 25,
        'stock_quantity' => 0,
        'specs' => [
            ['label' => ' Power ', 'value' => ' 750 W '],
            ['label' => 'Blank row', 'value' => ''],
            ['label' => '', 'value' => 'Industrial grade'],
            ['label' => 'Disc', 'value' => '115mm'],
        ],
    ])->assertCreated();

    expect($response->json('data.specs'))->toBe([
        ['label' => 'Power', 'value' => '750 W'],
        ['label' => '', 'value' => 'Industrial grade'],
        ['label' => 'Disc', 'value' => '115mm'],
    ]);
});

it('replaces a variant\'s details on update, and clears them when emptied', function () {
    $variant = ProductVariant::create([
        'product_id' => $this->product->id, 'sku' => 'GR-900', 'price' => 27, 'stock_quantity' => 0,
        'specs' => [['label' => 'Power', 'value' => '900W']],
    ]);

    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/product-variants/'.$variant->id, [
            'specs' => [['label' => 'Disc', 'value' => '115mm'], ['label' => 'Power', 'value' => '900W']],
        ])
        ->assertOk()
        ->assertJsonPath('data.specs.0.label', 'Disc');

    // A price edit that doesn't send specs leaves them alone.
    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/product-variants/'.$variant->id, ['price' => 28])
        ->assertOk();
    expect($variant->fresh()->specs)->toHaveCount(2);

    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/product-variants/'.$variant->id, ['specs' => [['label' => 'Power', 'value' => '  ']]])
        ->assertOk();
    expect($variant->fresh()->specs)->toBeNull();
});

it('refuses details that are not a list of label/value rows', function () {
    $variant = ProductVariant::create([
        'product_id' => $this->product->id, 'sku' => 'GR-1000', 'price' => 32, 'stock_quantity' => 0,
    ]);

    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/product-variants/'.$variant->id, [
            'specs' => [['label' => 'Power', 'value' => str_repeat('x', 300)]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['specs.0.value']);
});

it('shows a variant\'s details on the public product page', function () {
    ProductVariant::create([
        'product_id' => $this->product->id, 'sku' => 'GR-2400', 'size' => '230mm', 'price' => 65, 'stock_quantity' => 0,
        'specs' => [['label' => 'Power', 'value' => '2400W']],
    ]);

    $this->getJson('/api/v1/products/grinder')
        ->assertOk()
        ->assertJsonPath('data.variants.0.specs', [['label' => 'Power', 'value' => '2400W']]);
});
