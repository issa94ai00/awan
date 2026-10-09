<?php

use App\Models\Category;
use App\Models\User;

/**
 * The admin categories screen.
 *
 * It used to share the storefront's index, so a category switched off vanished
 * from the only screen that could switch it back on, and its edit form 404'd.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->token = $this->admin->createToken('test-token')->plainTextToken;
    $this->api = fn () => $this->withHeader('Authorization', "Bearer {$this->token}");

    $this->section = Category::create(['name_ar' => 'قسم', 'name_en' => 'Section', 'slug' => 'section', 'is_active' => true]);
    $this->child = Category::create(['name_ar' => 'فرعي', 'name_en' => 'Child', 'slug' => 'child', 'parent_id' => $this->section->id, 'is_active' => true]);
    $this->hidden = Category::create(['name_ar' => 'مخفي', 'name_en' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
});

test('the admin list includes inactive categories while the storefront does not', function () {
    $admin = ($this->api)()->getJson('/api/v1/admin/categories')->assertOk()->json('data');
    expect(collect($admin)->pluck('id'))->toContain($this->hidden->id);

    $section = collect($admin)->firstWhere('id', $this->section->id);
    expect($section['children_count'])->toBe(1);

    $public = $this->getJson('/api/v1/categories')->assertOk()->json('data');
    expect(collect($public)->pluck('id'))->not->toContain($this->hidden->id);
});

test('an inactive category can still be opened for editing', function () {
    ($this->api)()->getJson("/api/v1/admin/categories/{$this->hidden->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

test('a category can be filed under a top-level section', function () {
    ($this->api)()->putJson("/api/v1/admin/categories/{$this->hidden->id}", ['parent_id' => $this->section->id])
        ->assertOk();

    expect($this->hidden->fresh()->parent_id)->toBe($this->section->id);
});

test('the taxonomy stays two levels deep', function () {
    // Under a subcategory.
    ($this->api)()->putJson("/api/v1/admin/categories/{$this->hidden->id}", ['parent_id' => $this->child->id])
        ->assertStatus(422);

    // A section that has subcategories moved under another section.
    ($this->api)()->putJson("/api/v1/admin/categories/{$this->section->id}", ['parent_id' => $this->hidden->id])
        ->assertStatus(422);

    // Under itself.
    ($this->api)()->putJson("/api/v1/admin/categories/{$this->hidden->id}", ['parent_id' => $this->hidden->id])
        ->assertStatus(422);
});
