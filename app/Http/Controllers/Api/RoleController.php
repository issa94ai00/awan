<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use App\Services\RoleAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Roles (الأدوار), backed by spatie/laravel-permission.
 *
 * Who may reach each action is decided by the `permission:roles.*` gates on the
 * routes; what they may do with it (no touching super roles, no granting what
 * you do not hold) by AccessControlService.
 */
class RoleController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private AccessControlService $access,
        private RoleAssignmentService $assignments,
    ) {}

    /**
     * The roles, the permission catalogue grouped by module, and the counters
     * the screen shows — everything the list and the editor need at once.
     */
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions:id')
            ->withCount(['users', 'permissions', 'features'])
            ->orderByRaw("CASE name WHEN 'master' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
            ->orderBy('display_name')
            ->get()
            ->map(fn (Role $role) => $this->present($role));

        $permissions = Permission::orderBy('module')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'roles' => $roles,
                'modules' => $this->groupByModule($permissions),
                'stats' => [
                    'total' => $roles->count(),
                    'active' => $roles->where('is_active', true)->count(),
                    'custom' => $roles->where('is_system', false)->count(),
                    'permissions' => $permissions->count(),
                    'users_with_roles' => DB::table('model_has_roles')
                        ->where('model_type', (new User)->getMorphClass())
                        ->distinct()
                        ->count('model_id'),
                ],
            ],
        ]);
    }

    /** One role with the accounts that hold it. */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions:id')->loadCount(['users', 'permissions', 'features']);

        $users = $role->users()
            ->select('users.id', 'users.name', 'users.email', 'users.role_id')
            ->orderBy('users.name')
            ->limit(200)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_primary' => (int) $user->role_id === $role->id,
            ]);

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $this->present($role) + ['users' => $users],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')],
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => 'boolean',
            'permission_ids' => 'array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ], [
            'name.regex' => 'المعرّف يبدأ بحرف إنجليزي صغير ويحتوي أحرفاً صغيرة وأرقاماً و _ فقط.',
        ]);

        $permissionIds = $validated['permission_ids'] ?? [];
        $this->access->assertCanGrantPermissions($request->user(), $permissionIds);

        $role = DB::transaction(function () use ($validated, $permissionIds) {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'color' => $validated['color'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'is_system' => false,
            ]);
            $role->syncPermissions($permissionIds);

            return $role;
        });

        $this->audit->log(
            action: AuditLog::ACTION_CREATE,
            entityType: Role::class,
            entityId: $role->id,
            description: "إنشاء دور: {$role->display_name} ({$role->name})",
            newValues: ['name' => $role->name, 'permission_ids' => $permissionIds],
            module: AuditLog::MODULE_SECURITY,
        );

        return $this->respondWith($role, 'تم إنشاء الدور بنجاح', 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->access->assertCanManageRole($request->user(), $role);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'display_name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => 'sometimes|boolean',
        ]);

        // The code checks system roles by name; renaming one would silently
        // break every gate that names it.
        if ($role->is_system && isset($validated['name']) && $validated['name'] !== $role->name) {
            abort(422, 'لا يمكن تغيير معرّف دور أساسي في النظام.');
        }
        if ($role->isSuper() && array_key_exists('is_active', $validated) && ! $validated['is_active']) {
            abort(422, 'لا يمكن تعطيل دور المدير العام.');
        }

        $old = $role->only(array_keys($validated));
        $role->update($validated);

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: Role::class,
            entityId: $role->id,
            description: "تعديل دور: {$role->display_name}",
            oldValues: $old,
            newValues: $validated,
            module: AuditLog::MODULE_SECURITY,
        );

        return $this->respondWith($role, 'تم تحديث الدور بنجاح');
    }

    /** Replace the role's permissions with exactly the given set. */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $this->assertPermissionsEditable($request->user(), $role);

        $old = $role->permissions()->pluck('permissions.id')->all();
        $new = collect($validated['permission_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();

        // Only what is being added needs to be held by the editor; removing a
        // permission never escalates anyone.
        $this->access->assertCanGrantPermissions($request->user(), array_diff($new, $old));

        $role->syncPermissions($new);

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: Role::class,
            entityId: $role->id,
            description: "تحديث صلاحيات الدور: {$role->display_name}",
            oldValues: ['permission_ids' => $old],
            newValues: ['permission_ids' => $new],
            module: AuditLog::MODULE_SECURITY,
            metadata: [
                'added' => array_values(array_diff($new, $old)),
                'removed' => array_values(array_diff($old, $new)),
            ],
        );

        return $this->respondWith($role, 'تم تحديث صلاحيات الدور');
    }

    /**
     * Grant or revoke permissions one cell — or one module row — at a time,
     * for the matrix. `permission_ids` takes several at once.
     */
    public function togglePermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids' => 'required|array|min:1',
            'permission_ids.*' => 'integer|exists:permissions,id',
            'granted' => 'required|boolean',
        ]);

        $this->assertPermissionsEditable($request->user(), $role);

        $ids = collect($validated['permission_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $permissions = Permission::whereIn('id', $ids)->get();

        if ($validated['granted']) {
            $this->access->assertCanGrantPermissions($request->user(), $ids);
            $role->givePermissionTo($permissions);
        } else {
            $role->revokePermissionTo($permissions);
        }

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: Role::class,
            entityId: $role->id,
            description: ($validated['granted'] ? 'منح' : 'سحب')." صلاحيات للدور {$role->display_name}: ".$permissions->pluck('name')->implode(', '),
            newValues: ['permission_ids' => $ids, 'granted' => $validated['granted']],
            module: AuditLog::MODULE_SECURITY,
        );

        return $this->respondWith($role, $validated['granted'] ? 'تم منح الصلاحية' : 'تم سحب الصلاحية');
    }

    /** Add accounts to the role from its Members tab. */
    public function addMembers(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:200',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $role->load('permissions:id');
        $this->access->assertCanAssignRoles($request->user(), collect([$role]));

        $added = 0;
        foreach (User::whereIn('id', $validated['user_ids'])->get() as $user) {
            $added += count($this->assignments->addRoles($request->user(), $user, collect([$role])));
        }

        return response()->json([
            'success' => true,
            'message' => $added ? "تمت إضافة {$added} مستخدم إلى الدور" : 'المستخدمون المختارون يحملون الدور مسبقاً',
            'data' => ['added' => $added],
        ]);
    }

    /** Take the role away from one account; their other roles stay. */
    public function removeMember(Request $request, Role $role, User $user): JsonResponse
    {
        $removed = $this->assignments->removeRole($request->user(), $user, $role);

        return response()->json([
            'success' => true,
            'message' => $removed ? 'تم سحب الدور من المستخدم' : 'المستخدم لا يحمل هذا الدور',
            'data' => ['removed' => $removed],
        ]);
    }

    /** Copy a role — its permissions and its feature grants — under a new name. */
    public function duplicate(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')],
            'display_name' => 'nullable|string|max:100',
        ]);

        if ($role->isSuper()) {
            abort(422, 'لا يمكن نسخ دور المدير العام؛ صلاحياته لا تأتي من قائمة.');
        }

        $permissionIds = $role->permissions()->pluck('permissions.id')->all();
        $this->access->assertCanGrantPermissions($request->user(), $permissionIds);

        $name = $validated['name'] ?? $this->uniqueName($role->name.'_copy');

        $copy = DB::transaction(function () use ($role, $validated, $name, $permissionIds) {
            $copy = Role::create([
                'name' => $name,
                'guard_name' => $role->guard_name,
                'display_name' => $validated['display_name'] ?? $role->display_name.' (نسخة)',
                'description' => $role->description,
                'color' => $role->color,
                'is_active' => $role->is_active,
                'is_system' => false,
            ]);
            $copy->syncPermissions($permissionIds);
            $copy->features()->sync($role->features()->pluck('features.id')->all());

            return $copy;
        });

        $this->audit->log(
            action: AuditLog::ACTION_CREATE,
            entityType: Role::class,
            entityId: $copy->id,
            description: "نسخ الدور {$role->name} إلى {$copy->name}",
            newValues: ['source_role_id' => $role->id, 'permission_ids' => $permissionIds],
            module: AuditLog::MODULE_SECURITY,
        );

        return $this->respondWith($copy, 'تم نسخ الدور بنجاح', 201);
    }

    /**
     * Delete a custom role. If anyone holds it, `reassign_to` must name the
     * role they move to; deleting would otherwise strip their access silently.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->access->assertCanManageRole($request->user(), $role);

        if ($role->is_system) {
            abort(422, 'لا يمكن حذف دور أساسي في النظام.');
        }

        $validated = $request->validate([
            'reassign_to' => ['nullable', 'integer', 'exists:roles,id', Rule::notIn([$role->id])],
        ]);

        $holders = $role->users()->get();
        $target = isset($validated['reassign_to']) ? Role::with('permissions:id')->find($validated['reassign_to']) : null;

        if ($holders->isNotEmpty() && ! $target) {
            return response()->json([
                'success' => false,
                'message' => "هذا الدور مُسند إلى {$holders->count()} مستخدم. اختر دوراً بديلاً لنقلهم إليه قبل الحذف.",
                'data' => ['users_count' => $holders->count()],
            ], 422);
        }
        if ($target) {
            $this->access->assertCanAssignRoles($request->user(), collect([$target]));
        }

        DB::transaction(function () use ($role, $holders, $target) {
            foreach ($holders as $user) {
                $user->assignRole($target);
                if ((int) $user->role_id === $role->id) {
                    $user->forceFill(['role_id' => $target->id])->save();
                }
            }
            $role->delete();
        });

        $this->audit->log(
            action: AuditLog::ACTION_DELETE,
            entityType: Role::class,
            entityId: $role->id,
            description: "حذف الدور {$role->display_name} ({$role->name})".($target ? " ونقل {$holders->count()} مستخدم إلى {$target->name}" : ''),
            oldValues: $role->only(['name', 'display_name']),
            newValues: $target ? ['reassigned_to' => $target->id, 'user_ids' => $holders->pluck('id')->all()] : null,
            module: AuditLog::MODULE_SECURITY,
        );

        return response()->json(['success' => true, 'message' => 'تم حذف الدور', 'data' => null]);
    }

    private function assertPermissionsEditable(User $actor, Role $role): void
    {
        $this->access->assertCanManageRole($actor, $role);

        if ($role->isSuper()) {
            abort(422, 'دور المدير العام يملك كل الصلاحيات تلقائياً ولا يُقيَّد بقائمة.');
        }
    }

    private function uniqueName(string $base): string
    {
        $name = substr($base, 0, 50);
        $i = 2;
        while (Role::where('name', $name)->exists()) {
            $name = substr($base, 0, 46).'_'.$i++;
        }

        return $name;
    }

    private function respondWith(Role $role, string $message, int $status = 200): JsonResponse
    {
        $role->load('permissions:id')->loadCount(['users', 'permissions', 'features']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->present($role),
        ], $status);
    }

    /** @return array<string, mixed> */
    private function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name ?: $role->name,
            'description' => $role->description,
            'color' => $role->color,
            'is_active' => (bool) $role->is_active,
            'is_system' => (bool) $role->is_system,
            'is_super' => $role->isSuper(),
            'users_count' => $role->users_count ?? 0,
            'permissions_count' => $role->permissions_count ?? 0,
            'features_count' => $role->features_count ?? 0,
            'permission_ids' => $role->permissions->pluck('id')->values(),
            'updated_at' => $role->updated_at,
        ];
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<int, array<string, mixed>>
     */
    private function groupByModule($permissions): array
    {
        return $permissions
            ->groupBy(fn (Permission $p) => $p->module ?: strtok($p->name, '.'))
            ->map(fn ($items, $module) => [
                'module' => $module,
                'permissions' => $items->map(fn (Permission $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'display_name' => $p->display_name ?: $p->name,
                    'description' => $p->description,
                    'is_active' => (bool) $p->is_active,
                    'is_system' => (bool) $p->is_system,
                ])->values(),
            ])
            ->values()
            ->all();
    }
}
