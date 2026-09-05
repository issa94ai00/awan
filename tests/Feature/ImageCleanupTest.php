<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\ImageStore;
use Illuminate\Support\Facades\Storage;

/**
 * Replacing a picture used to leave the old file on disk for good.
 *
 * Uploads land the moment a file is picked, so every replaced main image,
 * every photo dropped from a gallery and every deleted product left an orphan
 * behind — 15 of the 16 files in this installation's upload folder were
 * unreferenced when ImageStore was written. The sweeping is only safe because
 * of two rules, and both are what these tests are really about: shipped
 * catalogue art is never ours to delete, and a file another row still shows
 * stays where it is.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->product = Product::create([
        'name_ar' => 'خلاط مغسلة',
        'slug' => 'cleanup-mixer',
        'sku' => 'SKU-CLEAN-1',
        'price' => 100,
        'image_main' => 'uploads/old-main.jpg',
        'image_gallery' => json_encode([]),
    ]);

    Storage::disk('public')->put('uploads/old-main.jpg', 'x');
    Storage::disk('public')->put('uploads/new-main.jpg', 'x');
});

test('replacing the main image deletes the file it replaced', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$this->product->id, [
            'image_main' => 'uploads/new-main.jpg',
        ])
        ->assertOk();

    expect($this->product->refresh()->image_main)->toBe('uploads/new-main.jpg');

    Storage::disk('public')->assertMissing('uploads/old-main.jpg');
    Storage::disk('public')->assertExists('uploads/new-main.jpg');
});

test('a shipped catalogue picture is left alone when it is replaced', function () {
    // Most of this catalogue's photos are files committed under public/, shared
    // between products. Swapping one product's picture must not delete them.
    $this->product->update(['image_main' => 'images_items/generic/faucet.svg']);
    Storage::disk('public')->put('images_items/generic/faucet.svg', 'x');

    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$this->product->id, [
            'image_main' => 'uploads/new-main.jpg',
        ])
        ->assertOk();

    Storage::disk('public')->assertExists('images_items/generic/faucet.svg');
});

test('a file another product still shows is kept', function () {
    Product::create([
        'name_ar' => 'منتج آخر',
        'slug' => 'cleanup-other',
        'price' => 50,
        'image_main' => 'uploads/old-main.jpg',
    ]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$this->product->id, [
            'image_main' => 'uploads/new-main.jpg',
        ])
        ->assertOk();

    Storage::disk('public')->assertExists('uploads/old-main.jpg');
});

test('a gallery entry with an arabic name is recognised as still in use', function () {
    // json_encode() escapes both the slashes and every non-ASCII character, so
    // this path is stored as "uploads\/م….jpg". Matching the column as
    // text missed it entirely — and a reference that fails to match is a
    // picture deleted out from under a product.
    $arabic = 'uploads/منشار-حديد.jpg';

    Storage::disk('public')->put($arabic, 'x');
    $this->product->update(['image_gallery' => json_encode([$arabic])]);

    expect(ImageStore::isReferenced($arabic))->toBeTrue();
    expect(ImageStore::forget($arabic))->toBe(0);

    Storage::disk('public')->assertExists($arabic);
});

test('dropping a photo from the gallery deletes just that file', function () {
    Storage::disk('public')->put('uploads/shot-a.jpg', 'x');
    Storage::disk('public')->put('uploads/shot-b.jpg', 'x');

    $this->product->update([
        'image_gallery' => json_encode(['uploads/shot-a.jpg', 'uploads/shot-b.jpg']),
    ]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$this->product->id, [
            'image_gallery' => ['uploads/shot-b.jpg'],
        ])
        ->assertOk();

    Storage::disk('public')->assertMissing('uploads/shot-a.jpg');
    Storage::disk('public')->assertExists('uploads/shot-b.jpg');
});

test('setting a gallery photo as the main image keeps the file', function () {
    // "Set as main" copies the path into image_main, so it leaves the gallery
    // and arrives in another column in the same request.
    Storage::disk('public')->put('uploads/shot-a.jpg', 'x');
    $this->product->update(['image_gallery' => json_encode(['uploads/shot-a.jpg'])]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$this->product->id, [
            'image_main' => 'uploads/shot-a.jpg',
            'image_gallery' => [],
        ])
        ->assertOk();

    Storage::disk('public')->assertExists('uploads/shot-a.jpg');
});

test('deleting a product removes the uploads only it used', function () {
    Storage::disk('public')->put('uploads/shared.jpg', 'x');

    Product::create([
        'name_ar' => 'منتج آخر',
        'slug' => 'cleanup-shares',
        'price' => 50,
        'image_main' => 'uploads/shared.jpg',
    ]);

    $this->product->update(['image_gallery' => json_encode(['uploads/shared.jpg'])]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/products/'.$this->product->id)
        ->assertOk();

    Storage::disk('public')->assertMissing('uploads/old-main.jpg');
    Storage::disk('public')->assertExists('uploads/shared.jpg');
});

test('the delete endpoint refuses a file a record still shows', function () {
    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/upload', ['path' => 'uploads/old-main.jpg'])
        ->assertStatus(409);

    Storage::disk('public')->assertExists('uploads/old-main.jpg');

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/upload', ['path' => 'uploads/new-main.jpg'])
        ->assertOk();

    Storage::disk('public')->assertMissing('uploads/new-main.jpg');
});

test('a category image is stored as a path, not as the URL the form was handed', function () {
    $category = Category::create([
        'name_ar' => 'أدوات',
        'name_en' => 'Tools',
        'slug' => 'cleanup-tools',
        'image' => 'uploads/old-main.jpg',
    ]);

    $shown = $this->actingAs($this->admin)
        ->getJson('/api/v1/categories/'.$category->id)
        ->assertOk()
        ->json('data.image');

    // The edit form loads that absolute URL and posts it straight back.
    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/categories/'.$category->id, [
            'name_ar' => 'أدوات',
            'name_en' => 'Tools',
            'slug' => 'cleanup-tools',
            'image' => $shown,
        ])
        ->assertOk();

    expect($category->refresh()->image)->toBe('uploads/old-main.jpg');
    Storage::disk('public')->assertExists('uploads/old-main.jpg');
});

test('images:prune reports orphans without deleting, and --force removes them', function () {
    $this->artisan('images:prune', ['--days' => 0])
        ->expectsOutputToContain('uploads/new-main.jpg')
        ->assertSuccessful();

    // Reporting must not have touched anything.
    Storage::disk('public')->assertExists('uploads/new-main.jpg');

    $this->artisan('images:prune', ['--days' => 0, '--force' => true])
        ->assertSuccessful();

    Storage::disk('public')->assertMissing('uploads/new-main.jpg');
    // Still on the product, so still on disk.
    Storage::disk('public')->assertExists('uploads/old-main.jpg');
});
