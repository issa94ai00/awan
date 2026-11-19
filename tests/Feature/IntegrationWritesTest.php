<?php

use App\Models\IntegrationSetting;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('stores an integration setting for an admin', function () {
    $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin']);
    Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'role_id' => $role->id]));

    $this->postJson('/api/v1/integrations', ['channel_name' => 'x'])->assertStatus(422)
        ->assertJsonValidationErrors(['api_domain', 'access_token']);
    $this->postJson('/api/v1/integrations', [
        'channel_name' => 'x', 'api_domain' => 'shop.example.com', 'access_token' => 't', 'sync_stock' => 'yes',
    ])->assertStatus(422)->assertJsonValidationErrors('sync_stock');

    $this->postJson('/api/v1/integrations', [
        'channel_name' => 'shopify', 'api_domain' => 'shop.example.com', 'access_token' => 'tok123',
        'sync_stock' => true, 'sync_orders' => false,
    ])->assertCreated()->assertJsonPath('success', true);

    $row = IntegrationSetting::where('channel_name', 'shopify')->first();
    expect($row)->not->toBeNull()
        ->and($row->api_domain)->toBe('shop.example.com')
        ->and((bool) $row->sync_stock)->toBeTrue()
        ->and((bool) $row->sync_orders)->toBeFalse();
});

it('requires authentication to store an integration setting', function () {
    $this->postJson('/api/v1/integrations', [
        'channel_name' => 'shopify', 'api_domain' => 'd', 'access_token' => 't',
    ])->assertUnauthorized();
    expect(IntegrationSetting::count())->toBe(0);
});
