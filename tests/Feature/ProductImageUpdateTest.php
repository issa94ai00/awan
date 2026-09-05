<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Saving a product used to corrupt its images.
 *
 * The admin form is handed image_url()'s absolute URLs and posts them straight
 * back, and the only un-mapping it did was stripping a leading "/storage/" —
 * which never matched the catalogue's public/ paths (images_items/...). So
 * opening a product and pressing save rewrote the row as
 * "https://host/images_items/x.svg": the host baked into the data, and a value
 * none of the other readers expect. Separately, every upload for one product
 * was written to "{slug}.{ext}", so a gallery photo overwrote the main image,
 * each gallery photo overwrote the one before it, and replacing an image left
 * the URL unchanged so browsers kept serving the old picture.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->product = Product::create([
        'name_ar' => 'خلاط مغسلة',
        'slug' => 'kitchen-mixer',
        'sku' => 'SKU-IMG-1',
        'price' => 100,
        'image_main' => 'images_items/generic/faucet.svg',
        'image_gallery' => json_encode(['uploads/faucet-a.jpg']),
    ]);
});

test('re-saving a product with the URLs the API handed it keeps the stored paths', function () {
    $shown = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/products/'.$this->product->id)
        ->assertOk()
        ->json('data');

    // Exactly what the form posts back when nobody touches the images tab.
    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$this->product->id, [
            'image_main' => $shown['image_main'],
            'image_gallery' => $shown['image_gallery'],
        ])
        ->assertOk();

    $this->product->refresh();

    expect($this->product->image_main)->toBe('images_items/generic/faucet.svg');
    expect(json_decode($this->product->image_gallery, true))->toBe(['uploads/faucet-a.jpg']);
});

test('the gallery is stored without blanks, duplicates or host names', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$this->product->id, [
            'image_gallery' => [
                config('app.url').'/storage/uploads/faucet-b.jpg',
                '/storage/uploads/faucet-b.jpg',
                '',
                'uploads/faucet-c.jpg',
            ],
        ])
        ->assertOk();

    expect(json_decode($this->product->refresh()->image_gallery, true))
        ->toBe(['uploads/faucet-b.jpg', 'uploads/faucet-c.jpg']);
});

test('clearing the main image on the form clears the column', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$this->product->id, ['image_main' => null])
        ->assertOk();

    expect($this->product->refresh()->image_main)->toBeNull();
});

test('a request that says nothing about images leaves them alone', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/products/'.$this->product->id, ['price' => 155])
        ->assertOk();

    $this->product->refresh();

    expect((float) $this->product->price)->toBe(155.0);
    expect($this->product->image_main)->toBe('images_items/generic/faucet.svg');
});

test('two uploads for the same product never overwrite each other', function () {
    Storage::fake('public');

    $paths = collect(['first.jpg', 'second.jpg'])->map(function ($name) {
        return $this->actingAs($this->admin)
            ->postJson('/api/v1/upload', [
                'file' => UploadedFile::fake()->image($name, 40, 40),
                'slug' => 'kitchen-mixer',
            ])
            ->assertOk()
            ->json('data.path');
    });

    expect($paths[0])->not->toBe($paths[1]);
    // The slug is still readable in the name — it just is not the whole name.
    expect($paths[0])->toStartWith('uploads/kitchen-mixer-');

    Storage::disk('public')->assertExists($paths[0]);
    Storage::disk('public')->assertExists($paths[1]);
});

test('the delete endpoint refuses paths outside the upload folder', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/upload', ['path' => '../../.env'])
        ->assertStatus(422);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/upload', ['path' => 'images_items/generic/faucet.svg'])
        ->assertStatus(422);
});
