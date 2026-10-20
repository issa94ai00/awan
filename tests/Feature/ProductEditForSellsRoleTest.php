<?php

use App\Models\Feature;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;

/**
 * The sells role adds products and looks at them, but may not change or
 * delete one. Other staff keep editing as before.
 */
beforeEach(function () {
    $sells = Role::firstOrCreate(['name' => 'sells', 'guard_name' => 'web'], ['display_name' => 'مبيعات', 'is_active' => true]);
    $sells->features()->syncWithoutDetaching(Feature::pluck('id'));

    $this->seller = User::factory()->create();
    $this->seller->roles()->syncWithoutDetaching([$sells->id]);

    $this->product = Product::factory()->create(['price' => 100]);
});

it('lets the sells role view and add products', function () {
    $this->actingAs($this->seller)->getJson("/api/v1/admin/products/{$this->product->id}")->assertOk();
    $this->actingAs($this->seller)->getJson('/api/v1/admin/products')->assertOk();

    // Past the role gate: the request reaches validation.
    $this->actingAs($this->seller)->postJson('/api/v1/admin/products', [])->assertUnprocessable();
});

it('refuses the sells role every change to an existing product', function (string $method, string $uri) {
    $variant = ProductVariant::create(['product_id' => $this->product->id, 'sku' => 'V-SELLS', 'price' => 100]);
    $uri = strtr($uri, ['{product}' => $this->product->id, '{variant}' => $variant->id]);

    $this->actingAs($this->seller)->json($method, $uri, ['price' => 1])->assertForbidden();
})->with([
    'update' => ['PUT', '/api/v1/admin/products/{product}'],
    'delete' => ['DELETE', '/api/v1/admin/products/{product}'],
    'reorder' => ['PUT', '/api/v1/admin/products/reorder'],
    'import' => ['POST', '/api/v1/admin/products/import'],
    'variant update' => ['PUT', '/api/v1/admin/product-variants/{variant}'],
    'variant delete' => ['DELETE', '/api/v1/admin/product-variants/{variant}'],
    'unit update' => ['PUT', '/api/v1/admin/products/{product}/units/1'],
    'unit delete' => ['DELETE', '/api/v1/admin/products/{product}/units/1'],
]);

it('leaves the product unchanged when the sells role tries', function () {
    $this->actingAs($this->seller)->putJson("/api/v1/admin/products/{$this->product->id}", ['price' => 1]);
    $this->actingAs($this->seller)->deleteJson("/api/v1/admin/products/{$this->product->id}");

    expect((float) $this->product->fresh()->price)->toBe(100.0);
});

it('tells the app the sells role may not edit products', function () {
    $this->actingAs($this->seller)->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.user.can_edit_products', false);
});

it('still lets other staff edit a product', function () {
    $this->actingAs(User::factory()->operator()->create())
        ->putJson("/api/v1/admin/products/{$this->product->id}", ['price' => 120])
        ->assertSuccessful();
});
