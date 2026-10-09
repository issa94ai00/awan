<?php

use App\Models\Setting;
use App\Models\User;

test('settings store the contact branches list and serve it publicly', function () {
    $branches = json_encode([
        ['name_ar' => 'المركز الرئيسي', 'name_en' => 'Head office', 'address_ar' => 'الرياض', 'address_en' => 'Riyadh', 'phone' => '', 'map_url' => '', 'is_main' => true],
        ['name_ar' => 'الفرع', 'name_en' => 'Branch', 'address_ar' => 'ريف دمشق - معربا', 'address_en' => 'Maaraba', 'phone' => '00963962889577', 'map_url' => 'https://maps.app.goo.gl/x', 'is_main' => false],
    ], JSON_UNESCAPED_UNICODE);

    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->postJson('/api/v1/settings', ['settings' => ['contact_branches' => $branches]])
        ->assertSuccessful();

    expect(Setting::get('contact_branches'))->toBe($branches);

    $served = json_decode($this->getJson('/api/v1/settings')->assertOk()->json('data.settings.contact_branches'), true);
    expect(array_column($served, 'name_en'))->toBe(['Head office', 'Branch']);
});

test('settings refuse a contact branches value that is not JSON', function () {
    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->postJson('/api/v1/settings', ['settings' => ['contact_branches' => 'Riyadh; Damascus']])
        ->assertStatus(422);
});
