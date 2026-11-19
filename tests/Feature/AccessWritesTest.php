<?php

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $master = Role::firstOrCreate(['name' => 'master'], ['display_name' => 'master']);
    $this->master = User::factory()->create(['role_id' => $master->id]);
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin']);
    $this->admin = User::factory()->create(['is_admin' => true, 'role_id' => $adminRole->id]);
    Sanctum::actingAs($this->master);
});

it('creates a permission and grants it to super roles', function () {
    $this->postJson('/api/v1/admin/access/permissions', ['display_name' => 'x'])->assertStatus(422);
    $this->postJson('/api/v1/admin/access/permissions', ['name' => 'Bad Name', 'display_name' => 'x'])->assertStatus(422);

    $this->postJson('/api/v1/admin/access/permissions', [
        'name' => 'widgets.export', 'display_name' => 'Export widgets',
    ])->assertCreated()->assertJsonPath('success', true);

    $this->assertDatabaseHas('permissions', ['name' => 'widgets.export', 'module' => 'widgets', 'is_system' => false]);
    $perm = Permission::where('name', 'widgets.export')->first();
    expect(Role::where('name', 'master')->first()->permissions->pluck('id'))->toContain($perm->id);

    $this->postJson('/api/v1/admin/access/permissions', ['name' => 'widgets.export', 'display_name' => 'dup'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('refuses permission creation without permissions.manage', function () {
    $role = Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells']);
    Sanctum::actingAs(User::factory()->create(['is_admin' => false, 'role_id' => $role->id]));

    $this->postJson('/api/v1/admin/access/permissions', ['name' => 'widgets.export', 'display_name' => 'x'])->assertForbidden();
    $this->assertDatabaseMissing('permissions', ['name' => 'widgets.export']);
});

it('syncs a role\'s permissions', function () {
    $role = Role::create(['name' => 'custom_role', 'display_name' => 'Custom', 'guard_name' => 'web']);
    $a = Permission::create(['name' => 'zzz.a', 'guard_name' => 'web', 'display_name' => 'A', 'module' => 'zzz']);
    $b = Permission::create(['name' => 'zzz.b', 'guard_name' => 'web', 'display_name' => 'B', 'module' => 'zzz']);
    $rid = $role->id;

    $this->putJson("/api/v1/admin/access/roles/$rid/permissions", [])->assertStatus(422);
    $this->putJson("/api/v1/admin/access/roles/$rid/permissions", ['permission_ids' => [999999]])->assertStatus(422);

    $this->putJson("/api/v1/admin/access/roles/$rid/permissions", ['permission_ids' => [$a->id, $b->id]])->assertOk();
    expect($role->fresh()->permissions->pluck('id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());

    $this->putJson("/api/v1/admin/access/roles/$rid/permissions", ['permission_ids' => [$a->id]])->assertOk();
    expect($role->fresh()->permissions->pluck('id')->all())->toBe([$a->id]);
});

it('refuses to restrict a super role\'s permission list', function () {
    $admin = Role::where('name', 'admin')->first();
    $rid = $admin->id;
    $this->putJson("/api/v1/admin/access/roles/$rid/permissions", ['permission_ids' => []])->assertStatus(422);
});

it('refuses role permission sync without roles.edit', function () {
    $role = Role::create(['name' => 'custom_role', 'display_name' => 'Custom', 'guard_name' => 'web']);
    $rid = $role->id;
    $sells = Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells']);
    Sanctum::actingAs(User::factory()->create(['is_admin' => false, 'role_id' => $sells->id]));

    $this->putJson("/api/v1/admin/access/roles/$rid/permissions", ['permission_ids' => []])->assertForbidden();
});

it('updates a feature and protects core features from being disabled', function () {
    $core = Feature::where('is_core', true)->first();
    $plain = Feature::where('is_core', false)->first();
    expect($core && $plain)->toBeTrue();
    $cid = $core->id;
    $pid = $plain->id;

    $this->putJson("/api/v1/admin/features/$pid", ['is_enabled' => 'maybe'])->assertStatus(422);
    $this->putJson("/api/v1/admin/features/$pid", ['is_enabled' => false, 'name_en' => 'Renamed'])->assertOk();
    $this->assertDatabaseHas('features', ['id' => $pid, 'is_enabled' => false, 'name_en' => 'Renamed']);

    $this->putJson("/api/v1/admin/features/$cid", ['is_enabled' => false])->assertStatus(422);
    $this->assertDatabaseHas('features', ['id' => $cid, 'is_enabled' => true]);
});

it('replaces a feature\'s roles and drops super roles', function () {
    $f = Feature::where('is_core', false)->first();
    $fid = $f->id;
    $r1 = Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells']);
    $adminRole = Role::where('name', 'admin')->first();
    $r1id = $r1->id;

    $this->putJson("/api/v1/admin/features/$fid/roles", [])->assertStatus(422);
    $this->putJson("/api/v1/admin/features/$fid/roles", ['role_ids' => [$r1->id, $adminRole->id]])->assertOk();
    expect($f->roles()->pluck('roles.id')->all())->toBe([$r1id]);

    $this->putJson("/api/v1/admin/features/$fid/roles", ['role_ids' => []])->assertOk();
    expect($f->roles()->count())->toBe(0);
});

it('toggles a single role on a feature', function () {
    $f = Feature::where('is_core', false)->first();
    $fid = $f->id;
    $r = Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells']);
    $adminRole = Role::where('name', 'admin')->first();

    $this->postJson("/api/v1/admin/features/$fid/roles/toggle", ['role_id' => $r->id])->assertStatus(422);

    $this->postJson("/api/v1/admin/features/$fid/roles/toggle", ['role_id' => $r->id, 'granted' => true])->assertOk();
    expect($f->roles()->where('roles.id', $r->id)->exists())->toBeTrue();

    $this->postJson("/api/v1/admin/features/$fid/roles/toggle", ['role_id' => $r->id, 'granted' => false])->assertOk();
    expect($f->roles()->where('roles.id', $r->id)->exists())->toBeFalse();

    // admin role is a no-op: bypasses the matrix, nothing stored
    $f->roles()->detach($adminRole->id);
    $this->postJson("/api/v1/admin/features/$fid/roles/toggle", ['role_id' => $adminRole->id, 'granted' => true])->assertOk();
    expect($f->roles()->where('roles.id', $adminRole->id)->exists())->toBeFalse();
});

it('bulk-toggles features but never disables core ones', function () {
    $core = Feature::where('is_core', true)->first();
    $plain = Feature::where('is_core', false)->first();

    $this->postJson('/api/v1/admin/features/bulk-toggle', ['keys' => ['nope_nope'], 'is_enabled' => false])->assertStatus(422);

    $this->postJson('/api/v1/admin/features/bulk-toggle', ['keys' => [$core->key, $plain->key], 'is_enabled' => false])->assertOk();
    $this->assertDatabaseHas('features', ['id' => $core->id, 'is_enabled' => true]);
    $this->assertDatabaseHas('features', ['id' => $plain->id, 'is_enabled' => false]);

    $this->postJson('/api/v1/admin/features/bulk-toggle', ['keys' => [$plain->key], 'is_enabled' => true])->assertOk();
    $this->assertDatabaseHas('features', ['id' => $plain->id, 'is_enabled' => true]);
});

it('group-toggles non-core features only when disabling', function () {
    $plain = Feature::where('is_core', false)->whereNotNull('group')->first();
    $group = $plain->group;
    $coreInGroup = Feature::where('group', $group)->where('is_core', true)->count();

    $this->postJson('/api/v1/admin/features/group-toggle', ['group' => $group])->assertStatus(422);
    $this->postJson('/api/v1/admin/features/group-toggle', ['group' => $group, 'is_enabled' => false])->assertOk();

    expect(Feature::where('group', $group)->where('is_core', false)->where('is_enabled', true)->count())->toBe(0);
    expect(Feature::where('group', $group)->where('is_core', true)->where('is_enabled', true)->count())->toBe($coreInGroup);

    $this->postJson('/api/v1/admin/features/group-toggle', ['group' => $group, 'is_enabled' => true])->assertOk();
    expect(Feature::where('group', $group)->where('is_enabled', false)->count())->toBe(0);
});

it('forbids feature management to a plain admin (master only)', function () {
    $f = Feature::where('is_core', false)->first();
    $fid = $f->id;
    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/admin/features/$fid", ['is_enabled' => false])->assertForbidden();
    $this->putJson("/api/v1/admin/features/$fid/roles", ['role_ids' => []])->assertForbidden();
    $this->postJson("/api/v1/admin/features/$fid/roles/toggle", ['role_id' => 1, 'granted' => true])->assertForbidden();
    $this->postJson('/api/v1/admin/features/bulk-toggle', ['keys' => [$f->key], 'is_enabled' => false])->assertForbidden();
    $this->postJson('/api/v1/admin/features/group-toggle', ['group' => 'x', 'is_enabled' => false])->assertForbidden();
    $this->assertDatabaseHas('features', ['id' => $fid, 'is_enabled' => true]);
});
