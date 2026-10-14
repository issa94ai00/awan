<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * The rules that stop the access screens from being a privilege-escalation
 * tool. The route gates (`permission:roles.edit`, ...) decide who may open a
 * screen; these decide what they may do with it:
 *
 *  - Only super admins may touch the super roles (master, admin) or the
 *    accounts holding them, and only a master may hand out or take away master.
 *  - Anyone else may only grant permissions they hold themselves, and only
 *    assign roles whose permissions they hold.
 *  - Nobody may take super-admin access away from themselves, and the last
 *    master account cannot lose the role.
 *
 * Each check aborts with a 403 that says which rule it hit.
 */
class AccessControlService
{
    public function assertCanManageRole(User $actor, Role $role): void
    {
        if ($role->isSuper() && ! $actor->isSuperAdmin()) {
            abort(403, 'لا يمكن تعديل أدوار المدير العام إلا من قبل مدير عام.');
        }

        if ($role->name === 'master' && ! $actor->isMaster()) {
            abort(403, 'دور المدير الرئيسي لا يعدّله إلا مدير رئيسي.');
        }
    }

    /**
     * @param  iterable<int, int>  $permissionIds  the ids being granted
     */
    public function assertCanGrantPermissions(User $actor, iterable $permissionIds): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $own = $actor->effectivePermissionNames();
        $missing = Permission::whereIn('id', collect($permissionIds)->all())
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, $own, true))
            ->values();

        if ($missing->isNotEmpty()) {
            abort(403, 'لا يمكنك منح صلاحيات لا تملكها: '.$missing->implode('، '));
        }
    }

    /**
     * @param  Collection<int, Role>  $roles  the roles being handed out
     */
    public function assertCanAssignRoles(User $actor, Collection $roles): void
    {
        foreach ($roles as $role) {
            $this->assertCanManageRole($actor, $role);
        }

        $this->assertCanGrantPermissions(
            $actor,
            $roles->flatMap(fn (Role $role) => $role->permissions->pluck('id'))->unique()
        );
    }

    public function assertCanManageUser(User $actor, User $target): void
    {
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            abort(403, 'لا يمكن تعديل حساب مدير عام إلا من قبل مدير عام.');
        }

        if ($target->isMaster() && ! $actor->isMaster()) {
            abort(403, 'حساب المدير الرئيسي لا يعدّله إلا مدير رئيسي.');
        }
    }

    /**
     * Guards a change of `$target`'s roles to `$newRoleIds`.
     *
     * @param  array<int, int>  $newRoleIds
     */
    public function assertRoleChangeIsSafe(User $actor, User $target, array $newRoleIds): void
    {
        $newNames = Role::whereIn('id', $newRoleIds)->pluck('name');
        $losesSuper = $target->holdsSuperRole()
            && $newNames->intersect(Role::SUPER_ROLES)->isEmpty()
            && ! $target->is_admin;

        if ($actor->is($target) && $losesSuper) {
            abort(403, 'لا يمكنك إزالة صلاحيات المدير العام عن حسابك.');
        }

        $losesMaster = $target->isMaster() && ! $newNames->contains('master');
        if ($losesMaster && $this->masterCount() <= 1) {
            abort(403, 'لا يمكن إزالة دور المدير الرئيسي عن آخر حساب يحمله.');
        }
    }

    public function masterCount(): int
    {
        // Through query(): User::role() is the primary-role relation, which
        // shadows Spatie's static role scope.
        return User::query()->role('master')->count();
    }

    public function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
