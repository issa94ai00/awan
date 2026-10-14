<?php

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and permissions on spatie/laravel-permission, and the access screens.
 *
 * Two halves. The model half pins down that the move kept every old rule —
 * `role_id` still names a role the user holds, super admins still pass
 * everything — and that the new ones hold: several roles, direct grants,
 * inactive roles and permissions granting nothing. The API half is mostly
 * about escalation: an account allowed onto a screen must not be able to use
 * it to hand itself, or anyone, more than it already holds.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function accessUser(string ...$roles): User
{
    $user = User::factory()->create([
        'is_admin' => false,
        'role_id' => $roles ? Role::findByName($roles[0], 'web')->id : null,
    ]);
    if (count($roles) > 1) {
        $user->assignRole(array_slice($roles, 1));
    }

    return $user->fresh();
}

function grantRole(string $role, string ...$permissions): void
{
    Role::findByName($role)->givePermissionTo($permissions);
}

// ---------------------------------------------------------------- model rules

it('mirrors role_id into the spatie roles, swapping only the primary', function () {
    $user = accessUser('sells');
    expect($user->hasRole('sells'))->toBeTrue();

    $user->assignRole('marketer');
    $user->update(['role_id' => Role::findByName('accountant', 'web')->id]);
    $user = $user->fresh();

    expect($user->getRoleNames()->sort()->values()->all())->toBe(['accountant', 'marketer']);
});

it('resolves permissions through roles and direct grants', function () {
    $user = accessUser('employee');

    expect($user->hasPermission('sales.view'))->toBeTrue()
        ->and($user->hasPermission('hr.manage'))->toBeFalse()
        ->and($user->hasPermission('no.such.permission'))->toBeFalse();

    $user->givePermissionTo('hr.manage');

    expect($user->fresh()->hasPermission('hr.manage'))->toBeTrue()
        ->and($user->fresh()->can('hr.manage'))->toBeTrue();
});

it('grants nothing through an inactive role or an inactive permission', function () {
    $user = accessUser('employee');

    Permission::findByName('sales.view', 'web')->update(['is_active' => false]);
    expect($user->fresh()->hasPermission('sales.view'))->toBeFalse()
        ->and($user->fresh()->hasPermission('dashboard.view'))->toBeTrue();

    Role::findByName('employee', 'web')->update(['is_active' => false]);
    expect($user->fresh()->hasPermission('dashboard.view'))->toBeFalse();
});

it('lets super admins through every permission check', function () {
    $admin = accessUser('admin');
    $flagged = User::factory()->create(['is_admin' => true, 'role_id' => null]);

    expect($admin->hasPermission('hr.manage'))->toBeTrue()
        ->and($flagged->can('roles.delete'))->toBeTrue()
        ->and($admin->isMaster())->toBeFalse()
        ->and(accessUser('master')->isMaster())->toBeTrue();
});

it('opens features granted to any of the roles a user holds', function () {
    $feature = Feature::where('is_core', false)->first();
    $feature->roles()->sync([Role::findByName('marketer', 'web')->id]);

    $user = accessUser('employee');
    expect(Feature::accessible($feature->key, $user))->toBeFalse();

    $user->assignRole('marketer');
    expect(Feature::accessible($feature->key, $user->fresh()))->toBeTrue()
        ->and(Feature::accessibleKeysFor($user->fresh()))->toContain($feature->key);
});

it('hands the SPA the roles and effective permissions of the signed-in user', function () {
    $user = accessUser('employee', 'marketer');

    $data = $this->actingAs($user)->getJson('/api/v1/auth/user')->assertOk()->json();
    $payload = data_get($data, 'data.user') ?? data_get($data, 'user') ?? data_get($data, 'data');

    expect(collect($payload['roles'])->pluck('name')->sort()->values()->all())->toBe(['employee', 'marketer'])
        ->and($payload['permissions'])->toContain('marketing.campaigns', 'sales.view')
        ->and($payload['permissions'])->not->toContain('hr.manage');
});

// ------------------------------------------------------------------ the gates

it('keeps the access screens away from an account without the permission', function () {
    $this->actingAs(accessUser('sells'))
        ->getJson('/api/v1/admin/access/roles')
        ->assertForbidden();
});

it('lets a role that was granted roles.view look but not change', function () {
    grantRole('manager', 'roles.view');
    $manager = accessUser('manager');

    $this->actingAs($manager)->getJson('/api/v1/admin/access/roles')->assertOk();
    $this->actingAs($manager)
        ->putJson('/api/v1/admin/access/roles/'.Role::findByName('sells', 'web')->id, ['display_name' => 'x'])
        ->assertForbidden();
});

// ----------------------------------------------------------------- escalation

it('refuses to let an editor grant a permission they do not hold', function () {
    grantRole('manager', 'roles.edit');
    $manager = accessUser('manager');
    $sells = Role::findByName('sells', 'web');

    $this->actingAs($manager)
        ->postJson("/api/v1/admin/access/roles/{$sells->id}/permissions/toggle", [
            'permission_ids' => [Permission::findByName('hr.manage', 'web')->id],
            'granted' => true,
        ])
        ->assertForbidden();
    expect($sells->fresh()->hasPermission('hr.manage'))->toBeFalse();

    // One the manager holds is fine.
    $this->actingAs($manager)
        ->postJson("/api/v1/admin/access/roles/{$sells->id}/permissions/toggle", [
            'permission_ids' => [Permission::findByName('inventory.manage', 'web')->id],
            'granted' => true,
        ])
        ->assertOk();
    expect($sells->fresh()->hasPermission('inventory.manage'))->toBeTrue();
});

it('refuses to let a non-super account touch the super roles', function () {
    grantRole('manager', 'roles.edit', 'users.view', 'users.assign_roles');
    $manager = accessUser('manager');
    $target = accessUser('employee');

    $this->actingAs($manager)
        ->putJson('/api/v1/admin/access/roles/'.Role::findByName('admin', 'web')->id, ['display_name' => 'mine'])
        ->assertForbidden();

    $this->actingAs($manager)
        ->putJson("/api/v1/admin/access/users/{$target->id}/access", [
            'role_ids' => [Role::findByName('admin', 'web')->id],
        ])
        ->assertForbidden();
    expect($target->fresh()->hasRole('admin'))->toBeFalse();
});

it('keeps the master role for masters to hand out', function () {
    $admin = accessUser('admin');
    $target = accessUser('employee');

    $this->actingAs($admin)
        ->putJson("/api/v1/admin/access/users/{$target->id}/access", [
            'role_ids' => [Role::findByName('master', 'web')->id],
        ])
        ->assertForbidden();

    $this->actingAs(accessUser('master'))
        ->putJson("/api/v1/admin/access/users/{$target->id}/access", [
            'role_ids' => [Role::findByName('master', 'web')->id],
        ])
        ->assertOk();
    expect($target->fresh()->isMaster())->toBeTrue();
});

it('does not let anyone strip their own super access or the last master', function () {
    $admin = accessUser('admin');
    $this->actingAs($admin)
        ->putJson("/api/v1/admin/access/users/{$admin->id}/access", [
            'role_ids' => [Role::findByName('sells', 'web')->id],
        ])
        ->assertForbidden();

    $master = accessUser('master');
    $other = accessUser('master');
    $this->actingAs($other)
        ->putJson("/api/v1/admin/access/users/{$master->id}/access", ['role_ids' => []])
        ->assertOk();
    $this->actingAs($other)
        ->putJson("/api/v1/admin/access/users/{$other->id}/access", [
            'role_ids' => [Role::findByName('admin', 'web')->id],
        ])
        ->assertForbidden();
});

// ------------------------------------------------------------ role lifecycle

it('creates, duplicates and deletes a custom role, moving its holders', function () {
    $master = accessUser('master');
    $perm = Permission::findByName('products.view', 'web');

    $id = $this->actingAs($master)
        ->postJson('/api/v1/admin/access/roles', [
            'name' => 'warehouse_lead',
            'display_name' => 'Warehouse lead',
            'permission_ids' => [$perm->id],
        ])
        ->assertCreated()
        ->json('data.id');

    $copyId = $this->actingAs($master)
        ->postJson("/api/v1/admin/access/roles/{$id}/duplicate")
        ->assertCreated()
        ->assertJsonPath('data.name', 'warehouse_lead_copy')
        ->json('data.id');
    expect(Role::find($copyId)->hasPermission('products.view'))->toBeTrue();

    $holder = accessUser('employee');
    $holder->update(['role_id' => $id]);

    $this->actingAs($master)->deleteJson("/api/v1/admin/access/roles/{$id}")
        ->assertStatus(422)
        ->assertJsonPath('data.users_count', 1);

    $this->actingAs($master)->deleteJson("/api/v1/admin/access/roles/{$id}", ['reassign_to' => $copyId])
        ->assertOk();

    $holder = $holder->fresh();
    expect(Role::find($id))->toBeNull()
        ->and($holder->role_id)->toBe($copyId)
        ->and($holder->hasRole('warehouse_lead_copy'))->toBeTrue();
});

it('protects built-in roles and permissions from renaming and deletion', function () {
    $master = accessUser('master');
    $sells = Role::findByName('sells', 'web');

    $this->actingAs($master)->putJson("/api/v1/admin/access/roles/{$sells->id}", ['name' => 'sales'])->assertStatus(422);
    $this->actingAs($master)->deleteJson("/api/v1/admin/access/roles/{$sells->id}")->assertStatus(422);

    $perm = Permission::findByName('sales.view', 'web');
    $this->actingAs($master)->putJson("/api/v1/admin/access/permissions/{$perm->id}", ['name' => 'sales.see'])->assertStatus(422);
    $this->actingAs($master)->deleteJson("/api/v1/admin/access/permissions/{$perm->id}")->assertStatus(422);

    // Describing and switching off a built-in is allowed.
    $this->actingAs($master)->putJson("/api/v1/admin/access/permissions/{$perm->id}", ['display_name' => 'See sales', 'is_active' => false])->assertOk();
    expect($perm->fresh()->is_active)->toBeFalse();
});

// --------------------------------------------------------------------- users

it('gives a user several roles with a primary among them, plus direct grants', function () {
    $master = accessUser('master');
    $user = accessUser('employee');
    $sells = Role::findByName('sells', 'web');
    $marketer = Role::findByName('marketer', 'web');
    $direct = Permission::findByName('hr.view', 'web');

    $this->actingAs($master)
        ->putJson("/api/v1/admin/access/users/{$user->id}/access", [
            'role_ids' => [$sells->id, $marketer->id],
            'primary_role_id' => $marketer->id,
            'permission_ids' => [$direct->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.primary_role_id', $marketer->id);

    $user = $user->fresh();
    expect($user->getRoleNames()->sort()->values()->all())->toBe(['marketer', 'sells'])
        ->and($user->role_id)->toBe($marketer->id)
        ->and($user->hasPermission('hr.view'))->toBeTrue();

    $this->actingAs($master)
        ->putJson("/api/v1/admin/access/users/{$user->id}/access", [
            'role_ids' => [$sells->id],
            'primary_role_id' => $marketer->id,
        ])
        ->assertStatus(422);

    $effective = collect($this->actingAs($master)->getJson("/api/v1/admin/access/users/{$user->id}")
        ->assertOk()
        ->json('data.effective_permissions'))->keyBy('name');
    expect($effective['hr.view']['granted'])->toBeTrue()
        ->and(collect($effective['hr.view']['sources'])->pluck('type')->all())->toBe(['direct'])
        ->and($effective['hr.manage']['granted'])->toBeFalse();
});

it('creates a staff account with its roles in one step', function () {
    $this->actingAs(accessUser('admin'))
        ->postJson('/api/v1/admin/access/users', [
            'name' => 'New clerk',
            'email' => 'clerk@example.test',
            'password' => 'secret-pass-1',
            'role_ids' => [Role::findByName('sells', 'web')->id],
        ])
        ->assertCreated();

    $user = User::where('email', 'clerk@example.test')->first();
    expect($user->hasRole('sells'))->toBeTrue()
        ->and($user->role_id)->toBe(Role::findByName('sells', 'web')->id);
});

// ------------------------------------------------- membership shortcuts

it('adds and removes role members, keeping the primary role among those held', function () {
    $master = accessUser('master');
    $user = accessUser('employee');
    $other = accessUser();
    $sells = Role::findByName('sells', 'web');

    $this->actingAs($master)
        ->postJson("/api/v1/admin/access/roles/{$sells->id}/members", ['user_ids' => [$user->id, $other->id]])
        ->assertOk()
        ->assertJsonPath('data.added', 2);

    expect($user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['employee', 'sells'])
        ->and($user->fresh()->role_id)->toBe(Role::findByName('employee', 'web')->id)
        ->and($other->fresh()->role_id)->toBe($sells->id);

    // Taking the primary away promotes a remaining role; the last one leaves none.
    $employee = Role::findByName('employee', 'web');
    $this->actingAs($master)->deleteJson("/api/v1/admin/access/roles/{$employee->id}/members/{$user->id}")->assertOk();
    $this->actingAs($master)->deleteJson("/api/v1/admin/access/roles/{$sells->id}/members/{$other->id}")->assertOk();

    expect($user->fresh()->role_id)->toBe($sells->id)
        ->and($user->fresh()->getRoleNames()->all())->toBe(['sells'])
        ->and($other->fresh()->role_id)->toBeNull()
        ->and($other->fresh()->roles)->toHaveCount(0);

    $entries = $this->actingAs($master)
        ->getJson("/api/v1/admin/access/activity?role_id={$sells->id}")
        ->assertOk()
        ->json('data.entries');
    expect($entries)->toHaveCount(3);
});

it('guards the membership shortcuts like the access editor', function () {
    grantRole('manager', 'users.view', 'users.assign_roles');
    $manager = accessUser('manager');
    $target = accessUser('employee');
    $admin = Role::findByName('admin', 'web');

    $this->actingAs($manager)
        ->postJson("/api/v1/admin/access/roles/{$admin->id}/members", ['user_ids' => [$target->id]])
        ->assertForbidden();
    expect($target->fresh()->hasRole('admin'))->toBeFalse();

    // The last master cannot be removed from the role.
    $master = accessUser('master');
    $this->actingAs($master)
        ->deleteJson('/api/v1/admin/access/roles/'.Role::findByName('master', 'web')->id."/members/{$master->id}")
        ->assertForbidden();

    $this->actingAs(accessUser('employee'))
        ->getJson('/api/v1/admin/access/activity')
        ->assertForbidden();
});

it('changes roles for several users at once and reports the ones it skips', function () {
    $admin = accessUser('admin');
    $plain = accessUser('employee');
    $masterUser = accessUser('master');
    $marketer = Role::findByName('marketer', 'web');

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/access/users/bulk-roles', [
            'user_ids' => [$plain->id, $masterUser->id],
            'role_ids' => [$marketer->id],
            'mode' => 'add',
        ])
        ->assertOk()
        ->assertJsonPath('data.changed', 1)
        ->assertJsonPath('data.skipped.0.id', $masterUser->id);

    expect($plain->fresh()->hasRole('marketer'))->toBeTrue()
        ->and($masterUser->fresh()->hasRole('marketer'))->toBeFalse();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/access/users/bulk-roles', [
            'user_ids' => [$plain->id],
            'role_ids' => [$marketer->id],
            'mode' => 'remove',
        ])
        ->assertOk()
        ->assertJsonPath('data.changed', 1);
    expect($plain->fresh()->hasRole('marketer'))->toBeFalse();
});
