<?php

use App\Models\PriceOfferList;
use App\Models\Product;
use App\Models\Role;
use App\Models\SpecialOffer;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->actingAs($this->admin);
    $this->product = Product::factory()->create();
});

it('creates a price-offer list with valid product keys and drops unknown ones', function () {
    $pid = $this->product->id;

    $this->postJson('/api/v1/admin/price-offer-lists', ['name' => 'قائمة'])->assertCreated();
    $this->postJson('/api/v1/admin/price-offer-lists', ['items' => []])->assertStatus(422);

    $this->postJson('/api/v1/admin/price-offer-lists', ['name' => 'عروض', 'items' => ["p-$pid", 'p-999999', 'junk', 'p-']])
        ->assertCreated()->assertJsonPath('data.items_count', 1);

    $list = PriceOfferList::where('name_ar', 'عروض')->first();
    expect($list->user_id)->toBe($this->admin->id)->and($list->name_en)->toBe('عروض')
        ->and($list->items()->where('product_id', $pid)->count())->toBe(1);
});

it('updates a price-offer list name and replaces its items, then deletes it', function () {
    $pid = $this->product->id;
    $list = PriceOfferList::create(['user_id' => $this->admin->id, 'name_ar' => 'قديم', 'name_en' => 'قديم']);
    $list->items()->create(['product_id' => $pid]);
    $id = $list->id;

    $this->putJson("/api/v1/admin/price-offer-lists/$id", ['name' => 'جديد', 'items' => []])->assertOk();
    expect($list->fresh()->name_ar)->toBe('جديد')->and($list->items()->count())->toBe(0);

    $this->putJson("/api/v1/admin/price-offer-lists/$id", ['items' => ["p-$pid"]])->assertOk();
    expect($list->fresh()->name_ar)->toBe('جديد')->and($list->items()->count())->toBe(1);

    $this->putJson("/api/v1/admin/price-offer-lists/$id", ['name' => str_repeat('x', 200)])->assertStatus(422);

    $this->deleteJson("/api/v1/admin/price-offer-lists/$id")->assertOk();
    expect(PriceOfferList::find($id))->toBeNull();
});

it('keeps another user\'s price-offer list untouched', function () {
    $owner = User::factory()->create();
    $list = PriceOfferList::create(['user_id' => $owner->id, 'name_ar' => 'ليس لي', 'name_en' => 'ليس لي']);
    $id = $list->id;

expect($this->putJson("/api/v1/admin/price-offer-lists/$id", ['name' => 'اختراق'])->status())->toBeGreaterThanOrEqual(400);
    expect($this->deleteJson("/api/v1/admin/price-offer-lists/$id")->status())->toBeGreaterThanOrEqual(400);
    expect($list->fresh()->name_ar)->toBe('ليس لي');
});

it('creates, updates, toggles and deletes a special offer', function () {
    $pid = $this->product->id;

    $this->postJson('/api/v1/admin/special-offers', ['title_en' => 'x'])->assertStatus(422);
    $this->postJson('/api/v1/admin/special-offers', [
        'title_ar' => 'x', 'description_ar' => 'y', 'start_date' => '2026-02-01', 'end_date' => '2026-01-01',
    ])->assertStatus(422);

    $res = $this->postJson('/api/v1/admin/special-offers', [
        'title_ar' => 'خصم الصيف', 'description_ar' => 'وصف', 'discount_percentage' => 15,
        'product_id' => $pid, 'is_active' => true,
    ])->assertSuccessful();
    $id = $res->json('data.id');

    $offer = SpecialOffer::find($id);
    expect($offer->title_ar)->toBe('خصم الصيف')->and($offer->discount_percentage)->toBe(15.0)
        ->and($offer->product_id)->toBe($pid)->and((bool) $offer->is_active)->toBeTrue();

    $this->postJson("/api/v1/admin/special-offers/$id", [
        'title_ar' => 'خصم الشتاء', 'description_ar' => 'وصف جديد', 'discount_percentage' => 30,
    ])->assertOk();
    $offer = $offer->fresh();
    expect($offer->title_ar)->toBe('خصم الشتاء')->and($offer->discount_percentage)->toBe(30.0)
        // Omitted is_active is saved as inactive by the controller.
        ->and((bool) $offer->is_active)->toBeFalse();

    $this->postJson("/api/v1/admin/special-offers/$id", ['title_ar' => 'x'])->assertStatus(422);

    $this->postJson("/api/v1/admin/special-offers/$id/toggle-status")->assertOk()->assertJsonPath('data.is_active', true);
    expect((bool) $offer->fresh()->is_active)->toBeTrue();
    $this->postJson("/api/v1/admin/special-offers/$id/toggle-status")->assertOk()->assertJsonPath('data.is_active', false);
    expect((bool) $offer->fresh()->is_active)->toBeFalse();

    $this->deleteJson("/api/v1/admin/special-offers/$id")->assertOk();
    expect(SpecialOffer::find($id))->toBeNull();
    $this->deleteJson("/api/v1/admin/special-offers/$id")->assertNotFound();
});
