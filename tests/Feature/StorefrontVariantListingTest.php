<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;

/**
 * The storefront lists each variant of a product as its own product: a floor
 * drain in three sizes shows as three cards, each with its own name, code,
 * price and stock. Admin listings keep one row per product.
 */
beforeEach(function () {
    $this->category = Category::create([
        'name_ar' => 'تصريف', 'name_en' => 'Drainage', 'slug' => 'drainage', 'is_active' => 1,
    ]);

    $this->drain = Product::create([
        'name_ar' => 'جريدة تصريف', 'name_en' => 'Floor drain', 'slug' => 'floor-drain',
        'sku' => 'FD-1', 'price' => 5, 'is_active' => 1, 'category_id' => $this->category->id,
    ]);
    ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-1-3', 'size' => '3"', 'price' => 0.65, 'stock_quantity' => 45]);
    ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-1-4', 'size' => '4"', 'price' => 0.95, 'stock_quantity' => 0]);
    ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-1-5', 'size' => '5"', 'price' => 0, 'stock_quantity' => 10]);

    $this->tap = Product::create([
        'name_ar' => 'حنفية', 'name_en' => 'Tap', 'slug' => 'tap',
        'sku' => 'TAP-1', 'price' => 20, 'is_active' => 1, 'category_id' => $this->category->id,
    ]);
});

test('the public product list returns one row per variant, plus products without variants', function () {
    $rows = collect($this->getJson('/api/v1/products?per_page=50')->assertOk()->json('data'));

    expect($rows)->toHaveCount(4);
    expect($rows->pluck('listing_key')->unique())->toHaveCount(4);

    $four = $rows->firstWhere('sku', 'FD-1-4');
    expect($four['id'])->toBe($this->drain->id)
        ->and($four['name_ar'])->toBe('جريدة تصريف - 4"')
        ->and((float) $four['price'])->toBe(0.95)
        ->and($four['in_stock'])->toBeFalse();

    // A variant with no price of its own sells at the product's price.
    expect((float) $rows->firstWhere('sku', 'FD-1-5')['price'])->toBe(5.0);

    $tap = $rows->firstWhere('sku', 'TAP-1');
    expect($tap['variant_id'])->toBeNull()->and($tap['name_ar'])->toBe('حنفية');
});

test('the pagination total counts variant rows', function () {
    $response = $this->getJson('/api/v1/products?per_page=2')->assertOk();

    expect($response->json('pagination.total'))->toBe(4)
        ->and($response->json('pagination.last_page'))->toBe(2);
});

test('a variant is found by its own code and sorted by its own price', function () {
    $found = $this->getJson('/api/v1/products?search=FD-1-4')->assertOk()->json('data');
    expect($found)->toHaveCount(1)->and($found[0]['sku'])->toBe('FD-1-4');

    $sorted = collect($this->getJson('/api/v1/products?sort=price_asc')->assertOk()->json('data'))->pluck('sku')->all();
    expect($sorted)->toBe(['FD-1-3', 'FD-1-4', 'FD-1-5', 'TAP-1']);

    $cheap = $this->getJson('/api/v1/products?max_price=1')->json('data');
    expect(collect($cheap)->pluck('sku')->sort()->values()->all())->toBe(['FD-1-3', 'FD-1-4']);
});

test('the category page lists variants too, and expand_variants=0 groups them', function () {
    $rows = $this->getJson('/api/v1/categories/drainage/products')->assertOk()->json('data.products');
    expect($rows)->toHaveCount(4);

    $grouped = $this->getJson('/api/v1/products?expand_variants=0')->assertOk()->json('data');
    expect($grouped)->toHaveCount(2);
});

test('the admin product list stays one row per product', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $rows = $this->actingAs($admin)->getJson('/api/v1/admin/products')->assertOk()->json('data');
    expect($rows)->toHaveCount(2);
});

test('adding a variant card to the cart adds that variant, at its own price', function () {
    $four = ProductVariant::where('sku', 'FD-1-4')->first();
    $five = ProductVariant::where('sku', 'FD-1-5')->first();

    // The test session isn't carried between requests; the cart is also
    // found by its user, so sign one in.
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/v1/cart/add', ['product_id' => $this->drain->id, 'variant_id' => $four->id, 'quantity' => 2])->assertOk();
    $this->postJson('/api/v1/cart/add', ['product_id' => $this->drain->id, 'variant_id' => $four->id, 'quantity' => 1])->assertOk();
    $this->postJson('/api/v1/cart/add', ['product_id' => $this->drain->id, 'variant_id' => $five->id, 'quantity' => 1])->assertOk();
    $this->postJson('/api/v1/cart/add', ['product_id' => $this->drain->id, 'quantity' => 1])->assertOk();

    $cart = $this->getJson('/api/v1/cart/data')->assertOk()->json('cart');
    $items = collect($cart['items']);

    // Two variants and the bare product are three separate lines.
    expect($items)->toHaveCount(3);

    $line = $items->firstWhere('variant_id', $four->id);
    expect($line['quantity'])->toBe(3)
        ->and((float) $line['price'])->toBe(0.95)
        ->and($line['product']['name_ar'])->toBe('جريدة تصريف - 4"')
        ->and($line['product']['sku'])->toBe('FD-1-4');

    // No price of its own: the product's price.
    expect((float) $items->firstWhere('variant_id', $five->id)['price'])->toBe(5.0);
    expect((float) $cart['total'])->toBe(3 * 0.95 + 5 + 5);
});

test('a variant of another product is refused', function () {
    $four = ProductVariant::where('sku', 'FD-1-4')->first();

    $this->postJson('/api/v1/cart/add', ['product_id' => $this->tap->id, 'variant_id' => $four->id, 'quantity' => 1])
        ->assertStatus(422);
});

test('checking out a variant line prices the order at the variant', function () {
    $four = ProductVariant::where('sku', 'FD-1-4')->first();

    $response = $this->postJson('/api/v1/purchase-requests', [
        'name' => 'زبون', 'phone' => '0999000111',
        'items' => [[
            'product_id' => $this->drain->id, 'variant_id' => $four->id,
            'product_name' => 'جريدة تصريف - 4"', 'quantity' => 4,
        ]],
    ])->assertCreated();

    expect((float) $response->json('data.total'))->toBe(3.8);

    $line = \App\Models\SalesOrderItem::latest('id')->first();
    expect($line->product_id)->toBe($this->drain->id)
        ->and((float) $line->unit_price)->toBe(0.95)
        ->and($line->description)->toBe('جريدة تصريف - 4"');
});
