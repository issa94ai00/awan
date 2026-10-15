<?php

use App\Models\Customer;
use App\Models\Feature;
use App\Models\Role;
use App\Models\User;

/**
 * Adding a client is its own permission (customers.create). A sales role
 * reads and adds clients even with the POS module — whose endpoints the client
 * screens use — switched off.
 */
beforeEach(function () {
    $sales = Feature::firstOrCreate(['key' => 'sales'], ['name_ar' => 'المبيعات', 'name_en' => 'Sales', 'group' => 'sales', 'is_enabled' => true]);
    Feature::updateOrCreate(['key' => 'pos'], ['name_ar' => 'نقطة البيع', 'name_en' => 'POS', 'group' => 'sales', 'is_enabled' => false]);

    $this->role = Role::firstOrCreate(
        ['name' => 'sells'],
        ['display_name' => 'مبيعات', 'guard_name' => 'web', 'is_active' => true]
    );
    $this->role->features()->syncWithoutDetaching([$sales->id]);

    $this->rep = User::factory()->create(['is_admin' => false, 'role_id' => $this->role->id]);
    $this->rep->roles()->sync([$this->role->id]);
});

test('the permission exists for the role editor', function () {
    expect(\Spatie\Permission\Models\Permission::where('name', 'customers.create')->exists())->toBeTrue();
});

test('a sales user with the permission lists and adds clients while POS is off', function () {
    Customer::create(['name' => 'عميل قائم', 'phone' => '0999000501']);
    $this->role->givePermissionTo('customers.create');

    $api = $this->actingAs($this->rep, 'sanctum');
    $api->getJson('/api/v1/pos/customers')->assertOk();
    $api->postJson('/api/v1/pos/customers', ['name' => 'عميل جديد', 'phone' => '0999000502'])->assertCreated();
});

test('without the permission a sales user can list clients but not add one', function () {
    $api = $this->actingAs($this->rep, 'sanctum');

    $api->getJson('/api/v1/pos/customers')->assertOk();
    $api->postJson('/api/v1/pos/customers', ['name' => 'عميل جديد', 'phone' => '0999000503'])->assertForbidden();
    expect(Customer::where('phone', '0999000503')->exists())->toBeFalse();
});

test('an admin adds clients without being granted anything', function () {
    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->postJson('/api/v1/pos/customers', ['name' => 'عميل المدير', 'phone' => '0999000504'])->assertCreated();
});
