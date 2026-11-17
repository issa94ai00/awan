<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::create([
        'name_ar' => 'تصنيف الاختبار',
        'name_en' => 'Test Category',
        'slug' => 'test-category',
        'is_active' => 1,
    ]);

    $this->product = Product::create([
        'name_ar' => 'منتج رئيسي',
        'name_en' => 'Main Product',
        'slug' => 'main-product',
        'sku' => 'MAIN-001',
        'barcode' => 'BAR-PARENT-999',
        'size' => 'L',
        'color' => 'Blue',
        'price' => 100,
        'cost_price' => 50,
        'is_active' => 1,
        'category_id' => $this->category->id,
    ]);

    $this->variantWithNulls = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'VAR-001',
        'barcode' => null, // Intentionally null to verify no leak from parent barcode BAR-PARENT-999
        'size' => null,    // Intentionally null to verify no leak from parent size 'L'
        'color' => null,   // Intentionally null to verify no leak from parent color 'Blue'
        'material' => 'Steel',
        'price' => 120,
        'cost_price' => 60,
        'stock_quantity' => 15,
        'specs' => [
            ['label' => 'الضمان', 'value' => 'سنتان'],
        ],
    ]);

    $this->variantWithValues = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'VAR-002',
        'barcode' => 'BAR-VAR-222',
        'size' => 'M',
        'color' => 'Red',
        'material' => 'Plastic',
        'price' => 110,
        'cost_price' => 55,
        'stock_quantity' => 5,
        'specs' => [
            ['label' => 'الوزن', 'value' => '500g'],
        ],
    ]);

    $this->otherProduct = Product::create([
        'name_ar' => 'منتج آخر',
        'name_en' => 'Other Product',
        'slug' => 'other-product',
        'sku' => 'OTHER-001',
        'price' => 80,
        'is_active' => 1,
        'category_id' => $this->category->id,
    ]);

    $this->otherVariant = ProductVariant::create([
        'product_id' => $this->otherProduct->id,
        'sku' => 'OTHER-VAR-1',
        'size' => 'XL',
        'price' => 90,
        'stock_quantity' => 10,
    ]);

    $this->admin = User::factory()->admin()->create();
});

test('requesting product with variant does not leak parent barcode, size or color when variant has nulls', function () {
    $response = $this->getJson("/api/v1/products/{$this->product->slug}?variant={$this->variantWithNulls->id}")
        ->assertOk()
        ->json('data');

    expect($response['variant_id'])->toBe($this->variantWithNulls->id)
        ->and($response['sku'])->toBe('VAR-001')
        ->and($response['barcode'])->toBeNull() // strictly null, not 'BAR-PARENT-999'
        ->and($response['size'])->toBeNull()    // strictly null, not 'L'
        ->and($response['color'])->toBeNull()   // strictly null, not 'Blue'
        ->and($response['material'])->toBe('Steel')
        ->and($response['parent_barcode'])->toBe('BAR-PARENT-999')
        ->and($response['parent_size'])->toBe('L')
        ->and($response['parent_color'])->toBe('Blue')
        ->and($response['specs'])->toBe([['label' => 'الضمان', 'value' => 'سنتان']]);
});

test('requesting product with variant returns variant specific fields when populated', function () {
    $response = $this->getJson("/api/v1/products/{$this->product->id}?variant_id={$this->variantWithValues->id}")
        ->assertOk()
        ->json('data');

    expect($response['variant_id'])->toBe($this->variantWithValues->id)
        ->and($response['sku'])->toBe('VAR-002')
        ->and($response['barcode'])->toBe('BAR-VAR-222')
        ->and($response['size'])->toBe('M')
        ->and($response['color'])->toBe('Red')
        ->and($response['material'])->toBe('Plastic')
        ->and((float) $response['price'])->toBe(110.0)
        ->and($response['stock_quantity'])->toBe(5);
});

test('requesting a variant of another product returns 404 constraint error', function () {
    $this->getJson("/api/v1/products/{$this->product->id}?variant={$this->otherVariant->id}")
        ->assertStatus(404);
});

test('requesting a non-existent variant returns 404', function () {
    $this->getJson("/api/v1/products/{$this->product->id}?variant=999999")
        ->assertStatus(404);
});

test('direct show endpoint returns variant own fields without parent leakage', function () {
    $response = $this->getJson("/api/v1/product-variants/{$this->variantWithNulls->id}")
        ->assertOk()
        ->json('data');

    expect($response['id'])->toBe($this->variantWithNulls->id)
        ->and($response['product_id'])->toBe($this->product->id)
        ->and($response['sku'])->toBe('VAR-001')
        ->and($response['barcode'])->toBeNull()
        ->and($response['size'])->toBeNull()
        ->and($response['color'])->toBeNull()
        ->and($response['material'])->toBe('Steel')
        ->and($response['specs'])->toBe([['label' => 'الضمان', 'value' => 'سنتان']])
        ->and($response['product']['id'])->toBe($this->product->id)
        ->and($response['product']['sku'])->toBe('MAIN-001')
        ->and($response['product']['barcode'])->toBe('BAR-PARENT-999');
});

test('direct show endpoint hides cost price from shoppers and reveals to staff', function () {
    $publicResp = $this->getJson("/api/v1/product-variants/{$this->variantWithValues->id}")
        ->assertOk()
        ->json('data');
    expect($publicResp)->not->toHaveKey('cost_price');

    $staffResp = $this->actingAs($this->admin)->getJson("/api/v1/admin/product-variants/{$this->variantWithValues->id}")
        ->assertOk()
        ->json('data');
    expect((float) $staffResp['cost_price'])->toBe(55.0);
});

test('creating duplicate variant options under same product is rejected', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/product-variants', [
        'product_id' => $this->product->id,
        'sku' => 'VAR-DUP',
        'size' => 'M',
        'color' => 'Red',
        'material' => 'Plastic', // Matches $this->variantWithValues
        'price' => 115,
        'stock_quantity' => 2,
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['size']);
});

test('creating same variant options under different product is allowed', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/product-variants', [
        'product_id' => $this->otherProduct->id,
        'sku' => 'OTHER-M-RED',
        'size' => 'M',
        'color' => 'Red',
        'material' => 'Plastic',
        'price' => 115,
        'stock_quantity' => 2,
    ])->assertCreated();
});

test('updating variant to conflict with another variant under same product is rejected', function () {
    $this->actingAs($this->admin)->putJson("/api/v1/admin/product-variants/{$this->variantWithNulls->id}", [
        'size' => 'M',
        'color' => 'Red',
        'material' => 'Plastic',
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['size']);
});

test('updating variant without conflicting options succeeds', function () {
    $this->actingAs($this->admin)->putJson("/api/v1/admin/product-variants/{$this->variantWithValues->id}", [
        'price' => 118,
    ])->assertOk();
});

test('creating variant with duplicate barcode is rejected', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/product-variants', [
        'product_id' => $this->otherProduct->id,
        'sku' => 'NEW-BAR-VAR',
        'barcode' => 'BAR-VAR-222', // already used on $this->variantWithValues
        'size' => 'Small',
        'price' => 45,
        'stock_quantity' => 1,
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['barcode']);
});
