<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\ProductIdentifiers;

/**
 * Slug and SKU used to be the two fields that made the product form unusable
 * against its own catalogue: 1,600 of the 1,804 products carry no SKU, 1,657
 * no English name and 1,360 an Arabic slug — while the API demanded a SKU on
 * every update, a slug on every create, and the form insisted the slug be
 * spelled in Latin. Editing almost any existing product returned a 422 about
 * a field nobody had touched.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->category = Category::create([
        'name_ar' => 'أدوات صحية',
        'name_en' => 'Sanitary',
        'slug' => 'sanitary-'.uniqid(),
    ]);
});

test('a product can be created from an Arabic name alone', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/products', [
            'name_ar' => 'كباسة شطاف كروم',
            'category_id' => $this->category->id,
            'price' => 120,
            'currency' => 'SYP',
            'stock_quantity' => 0,
        ])
        ->assertCreated();

    $product = Product::find($response->json('data.id'));

    // Derived, not transliterated: that is what the rest of the table holds.
    expect($product->slug)->toBe('كباسة-شطاف-كروم');
    expect($product->sku)->toBeNull();
    expect($product->name_en)->toBeNull();
});

test('a generated slug steps aside for one already taken', function () {
    Product::create(['name_ar' => 'صنبور', 'slug' => 'صنبور', 'price' => 1]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/products', [
            'name_ar' => 'صنبور',
            'category_id' => $this->category->id,
            'price' => 90,
            'currency' => 'SYP',
            'stock_quantity' => 0,
        ])
        ->assertCreated();

    expect($response->json('data.slug'))->toBe('صنبور-2');
});

test('a product stored without a SKU can still be edited', function () {
    $product = Product::create([
        'name_ar' => 'مقبض',
        'slug' => 'مقبض',
        'price' => 30,
        'sku' => null,
    ]);

    // Exactly what the form posts: every field it holds, SKU and English name
    // included, both empty.
    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$product->id, [
            'name_ar' => 'مقبض مطلي',
            'name_en' => '',
            'sku' => '',
            'slug' => 'مقبض',
            'price' => 35,
        ])
        ->assertOk();

    $product->refresh();

    expect($product->name_ar)->toBe('مقبض مطلي');
    expect((float) $product->price)->toBe(35.0);
    // An emptied field is absent, not an empty string that would collide with
    // the next emptied field on the unique index.
    expect($product->sku)->toBeNull();
    expect($product->name_en)->toBeNull();
});

test('clearing the slug field keeps the public URL rather than minting a new one', function () {
    $product = Product::create(['name_ar' => 'خلاط', 'slug' => 'خلاط-مغسلة', 'price' => 10]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$product->id, ['slug' => '', 'price' => 12])
        ->assertOk();

    expect($product->refresh()->slug)->toBe('خلاط-مغسلة');
});

test('two products emptied of their SKU do not collide on the unique index', function () {
    $first = Product::create(['name_ar' => 'أول', 'slug' => 'first-p', 'price' => 1, 'sku' => 'A-1']);
    $second = Product::create(['name_ar' => 'ثاني', 'slug' => 'second-p', 'price' => 1, 'sku' => 'A-2']);

    foreach ([$first, $second] as $product) {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/products/'.$product->id, ['sku' => ''])
            ->assertOk();
    }

    expect($first->refresh()->sku)->toBeNull();
    expect($second->refresh()->sku)->toBeNull();
});

test('the next-sku endpoint continues its own series and skips what is taken', function () {
    Product::create(['name_ar' => 'قديم', 'slug' => 'old-code', 'price' => 1, 'sku' => 'L182']);

    $first = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/products/next-sku')
        ->assertOk()
        ->json('data.sku');

    expect($first)->toBe('SKU-00001');

    Product::create(['name_ar' => 'جديد', 'slug' => 'new-code', 'price' => 1, 'sku' => $first]);

    expect(ProductIdentifiers::nextSku())->toBe('SKU-00002');
});

test('a slug typed by hand is cleaned up rather than rejected', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/products', [
            'name_ar' => 'وصلة',
            'slug' => 'PVC Elbow 1 1/4',
            'category_id' => $this->category->id,
            'price' => 15,
            'currency' => 'SYP',
            'stock_quantity' => 0,
        ])
        ->assertCreated();

    expect($response->json('data.slug'))->toBe('pvc-elbow-1-1-4');
});
