<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function cartProduct(float $price = 10, string $name = 'P'): Product
{
    return Product::create([
        'name_ar' => $name . ' ar', 'name_en' => $name, 'price' => $price,
        'stock_quantity' => 50, 'is_active' => true, 'is_featured' => false, 'in_stock' => true,
    ]);
}

/**
 * Sign in and give the user a cart. The session id changes on every request in
 * the test client, so the cart is matched through its owner instead (the
 * controller looks a cart up by session OR user).
 */
function sessionCart($test): Cart
{
    $user = User::factory()->create();
    $test->actingAs($user);

    return Cart::create(['session_id' => 'initial-' . $user->id, 'user_id' => $user->id]);
}

// Session-cart routes exist twice (web and /api/v1); run the same contract on both.
dataset('cart prefixes', [
    'web' => [''],
    'api' => ['/api/v1'],
]);

it('adds a product to the session cart and merges repeat adds', function (string $prefix) {
    $p = cartProduct(12.5);
    $this->actingAs(User::factory()->create());

    $this->postJson("$prefix/cart/add", [])->assertStatus(422)->assertJsonValidationErrors(['product_id', 'quantity']);
    $this->postJson("$prefix/cart/add", ['product_id' => $p->id, 'quantity' => 0])->assertStatus(422);
    $this->postJson("$prefix/cart/add", ['product_id' => 999999, 'quantity' => 1])->assertStatus(422);

    $this->postJson("$prefix/cart/add", ['product_id' => $p->id, 'quantity' => 2])
        ->assertOk()->assertJson(['success' => true, 'cart_count' => 2]);
    $this->postJson("$prefix/cart/add", ['product_id' => $p->id, 'quantity' => 3])
        ->assertOk()->assertJson(['success' => true]);

    expect(CartItem::count())->toBe(1);
    $item = CartItem::first();
    expect($item->product_id)->toBe($p->id)->and($item->quantity)->toBe(5)->and((float) $item->price)->toBe(12.5);
})->with('cart prefixes');

it('updates and removes an item in the session cart', function (string $prefix) {
    $p = cartProduct(10);
    $cart = sessionCart($this);
    $item = $cart->items()->create(['product_id' => $p->id, 'quantity' => 1, 'price' => 10]);
    $id = $item->id;

    $this->postJson("$prefix/cart/update/$id", ['quantity' => 0])->assertStatus(422);
    $this->postJson("$prefix/cart/update/$id", ['quantity' => 4])
        ->assertOk()->assertJson(['success' => true, 'subtotal' => 40]);
    $this->assertDatabaseHas('cart_items', ['id' => $id, 'quantity' => 4]);

    $this->postJson("$prefix/cart/remove/$id")->assertOk()->assertJson(['success' => true]);
    $this->assertDatabaseMissing('cart_items', ['id' => $id]);
    $this->postJson("$prefix/cart/remove/$id")->assertNotFound();
})->with('cart prefixes');

it('clears the session cart', function (string $prefix) {
    $p = cartProduct();
    $cart = sessionCart($this);
    $cart->items()->create(['product_id' => $p->id, 'quantity' => 1, 'price' => 10]);
    $cart->items()->create(['product_id' => cartProduct(5, 'Q')->id, 'quantity' => 2, 'price' => 5]);

    $this->postJson("$prefix/cart/clear")->assertOk()->assertJson(['success' => true]);
    expect($cart->items()->count())->toBe(0);
})->with('cart prefixes');

it('cannot touch an item in someone else\'s session cart', function (string $prefix) {
    $p = cartProduct();
    $theirs = Cart::create(['session_id' => 'someone-else-session', 'user_id' => User::factory()->create()->id]);
    $item = $theirs->items()->create(['product_id' => $p->id, 'quantity' => 1, 'price' => 10]);
    $id = $item->id;
    $this->actingAs(User::factory()->create());

    $this->postJson("$prefix/cart/update/$id", ['quantity' => 9])->assertNotFound();
    $this->postJson("$prefix/cart/remove/$id")->assertNotFound();
    $this->postJson("$prefix/cart/clear")->assertOk();

    $this->assertDatabaseHas('cart_items', ['id' => $id, 'quantity' => 1]);
})->with('cart prefixes');

it('rejects a variant that belongs to another product', function () {
    $p = cartProduct();
    $this->actingAs(User::factory()->create());

    $this->postJson('/cart/add', ['product_id' => $p->id, 'quantity' => 1, 'variant_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('variant_id');
    expect(CartItem::count())->toBe(0);
});

it('requires authentication for the flutter cart', function () {
    foreach (['add', 'update', 'remove', 'clear'] as $action) {
        $this->postJson("/api/v1/flutter/cart/$action", [])->assertUnauthorized();
    }
});

it('validates flutter cart input', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/flutter/cart/add', [])->assertStatus(422)->assertJsonValidationErrors(['product_id', 'quantity']);
    $this->postJson('/api/v1/flutter/cart/add', ['product_id' => 999999, 'quantity' => 1])->assertStatus(422);
    $this->postJson('/api/v1/flutter/cart/update', [])->assertStatus(422)->assertJsonValidationErrors(['cart_item_id', 'quantity']);
    $this->postJson('/api/v1/flutter/cart/update', ['cart_item_id' => 999999, 'quantity' => 1])->assertStatus(422);
    $this->postJson('/api/v1/flutter/cart/remove', [])->assertStatus(422)->assertJsonValidationErrors('cart_item_id');
    $this->postJson('/api/v1/flutter/cart/remove', ['cart_item_id' => 999999])->assertStatus(422);
});

// FlutterCartController::add writes through CartItem, whose $fillable has no
// user_id/size/color and whose table requires cart_id, so the insert fails.
// Pinned as actual behaviour, not desired behaviour.
it('currently fails server-side when the flutter cart adds an item', function () {
    Sanctum::actingAs(User::factory()->create());
    $p = cartProduct();

    $this->postJson('/api/v1/flutter/cart/add', ['product_id' => $p->id, 'quantity' => 1])->assertStatus(500);
    expect(CartItem::count())->toBe(0);
});

it('clears the flutter cart call without error', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/flutter/cart/clear')->assertOk()->assertJson(['success' => true, 'message' => 'Cart cleared']);
});
