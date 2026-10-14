<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adds and removes roles one account at a time, for the shortcuts that change
 * membership without opening the full access editor: a role's Members tab and
 * the bulk action on the users list.
 *
 * The same guards as the editor apply (AccessControlService), and `role_id`
 * — the primary role — always stays among the roles the user holds.
 */
class RoleAssignmentService
{
    public function __construct(
        private AccessControlService $access,
        private AuditService $audit,
    ) {}

    /**
     * Give `$user` the roles they do not already hold. The caller has already
     * checked that `$actor` may hand these roles out.
     *
     * @param  Collection<int, Role>  $roles
     * @return array<int, int> the role ids actually added
     */
    public function addRoles(User $actor, User $user, Collection $roles): array
    {
        $this->access->assertCanManageUser($actor, $user);

        $held = $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->all();
        $added = $roles->pluck('id')->map(fn ($id) => (int) $id)->diff($held)->values()->all();
        if (! $added) {
            return [];
        }

        DB::transaction(function () use ($user, $added) {
            $user->roles()->syncWithoutDetaching($added);
            if (! $user->role_id) {
                $user->forceFill(['role_id' => $added[0]])->saveQuietly();
            }
        });
        $user->unsetRelation('roles');

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: User::class,
            entityId: $user->id,
            description: "إضافة أدوار للحساب {$user->email}: ".$roles->whereIn('id', $added)->pluck('name')->implode(', '),
            oldValues: ['role_ids' => $held],
            newValues: ['role_ids' => array_values(array_merge($held, $added))],
            module: AuditLog::MODULE_USERS,
            metadata: ['added_role_ids' => $added, 'role_ids' => $added],
        );

        return $added;
    }

    /** Take `$role` away from `$user`; false when they did not hold it. */
    public function removeRole(User $actor, User $user, Role $role): bool
    {
        $this->access->assertCanManageUser($actor, $user);
        $this->access->assertCanManageRole($actor, $role);

        $held = $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->all();
        if (! in_array($role->id, $held, true)) {
            return false;
        }

        $remaining = array_values(array_diff($held, [$role->id]));
        $this->access->assertRoleChangeIsSafe($actor, $user, $remaining);

        DB::transaction(function () use ($user, $role, $remaining) {
            $user->roles()->detach($role->id);
            if ((int) $user->role_id === $role->id) {
                $user->forceFill(['role_id' => $remaining[0] ?? null])->saveQuietly();
            }
        });
        $user->unsetRelation('roles');

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: User::class,
            entityId: $user->id,
            description: "سحب الدور {$role->name} من الحساب {$user->email}",
            oldValues: ['role_ids' => $held],
            newValues: ['role_ids' => $remaining],
            module: AuditLog::MODULE_USERS,
            metadata: ['removed_role_ids' => [$role->id], 'role_ids' => [$role->id]],
        );

        return true;
    }
}
