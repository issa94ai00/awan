<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use App\Services\RoleAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Users & access (المستخدمون والصلاحيات): who holds which roles, which
 * permissions they were granted directly, and what that adds up to.
 *
 * A user may hold several roles. `role_id` stays the primary one — the badge
 * on screen and the role older code reads — and is always among them.
 */
class UserAccessController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private AccessControlService $access,
        private RoleAssignmentService $assignments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'role_id' => 'nullable|integer',
            'scope' => 'nullable|in:all,staff,no_role,multi_role,direct,super',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = User::query()
            ->with(['roles:id,name,display_name,color,is_active', 'role:id,name,display_name,color', 'permissions:id'])
            ->orderBy('name');

        if (! empty($validated['search'])) {
            $query->whereSearch(['name', 'email', 'phone'], $validated['search']);
        }
        if (! empty($validated['role_id'])) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', $validated['role_id']));
        }

        match ($validated['scope'] ?? 'all') {
            'staff' => $query->where(fn ($q) => $q->has('roles')->orWhere('is_admin', true)),
            'no_role' => $query->doesntHave('roles')->where('is_admin', false),
            'multi_role' => $query->has('roles', '>', 1),
            'direct' => $query->has('permissions'),
            'super' => $query->where(fn ($q) => $q->where('is_admin', true)
                ->orWhereHas('roles', fn ($r) => $r->whereIn('name', Role::SUPER_ROLES))),
            default => null,
        };

        $page = $query->paginate($validated['per_page'] ?? 15);
        $actor = $request->user();

        $morph = (new User)->getMorphClass();
        $stats = [
            'total' => User::count(),
            'with_roles' => DB::table('model_has_roles')->where('model_type', $morph)->distinct()->count('model_id'),
            'multi_role' => DB::table('model_has_roles')->where('model_type', $morph)
                ->select('model_id')->groupBy('model_id')->havingRaw('COUNT(*) > 1')->get()->count(),
            'direct_grants' => DB::table('model_has_permissions')->where('model_type', $morph)->distinct()->count('model_id'),
        ];

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'users' => collect($page->items())->map(fn (User $user) => $this->present($user, $actor)),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * One user, with every permission they end up holding and where each one
     * comes from: which roles, or a direct grant.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $user->load(['roles.permissions', 'permissions', 'role']);

        $sources = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $sources[$permission->id][] = [
                    'type' => 'role',
                    'id' => $role->id,
                    'label' => $role->display_name ?: $role->name,
                    'active' => (bool) $role->is_active,
                ];
            }
        }
        foreach ($user->permissions as $permission) {
            $sources[$permission->id][] = ['type' => 'direct', 'id' => null, 'label' => null, 'active' => true];
        }

        $isSuper = $user->isSuperAdmin();
        $effective = Permission::orderBy('module')->orderBy('name')->get()
            ->map(function (Permission $p) use ($sources, $isSuper) {
                $from = $sources[$p->id] ?? [];
                $granted = $p->is_active && ($isSuper || collect($from)->contains('active', true));

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'display_name' => $p->display_name ?: $p->name,
                    'module' => $p->module ?: strtok($p->name, '.'),
                    'is_active' => (bool) $p->is_active,
                    'granted' => $granted,
                    'via_super' => $isSuper,
                    'sources' => $from,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $this->present($user, $request->user()) + [
                'effective_permissions' => $effective->values(),
                'features' => Feature::accessibleKeysFor($user),
            ],
        ]);
    }

    /** Create a staff account with its roles in one step. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30|unique:users,phone',
            'password' => ['required', 'string', Password::min(8)],
            'role_ids' => 'array',
            'role_ids.*' => 'integer|exists:roles,id',
            'primary_role_id' => 'nullable|integer|exists:roles,id',
        ]);

        $roleIds = collect($validated['role_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $primary = $this->resolvePrimary($validated['primary_role_id'] ?? null, $roleIds->all());
        $roles = Role::with('permissions:id')->whereIn('id', $roleIds)->get();
        $this->access->assertCanAssignRoles($request->user(), $roles);

        $user = DB::transaction(function () use ($validated, $roleIds, $primary) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'role_id' => $primary,
            ]);
            $user->syncRoles($roleIds->all());

            return $user;
        });

        $this->audit->log(
            action: AuditLog::ACTION_CREATE,
            entityType: User::class,
            entityId: $user->id,
            description: "إنشاء حساب {$user->email} بالأدوار: ".$roles->pluck('name')->implode(', '),
            newValues: ['role_ids' => $roleIds->all(), 'primary_role_id' => $primary],
            module: AuditLog::MODULE_USERS,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحساب',
            'data' => $this->present($user->fresh(['roles', 'role', 'permissions']), $request->user()),
        ], 201);
    }

    /**
     * Set a user's roles, primary role and direct permissions. Any of the three
     * keys may be left out to keep what is there.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'integer|exists:roles,id',
            'primary_role_id' => 'sometimes|nullable|integer|exists:roles,id',
            'permission_ids' => 'sometimes|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $actor = $request->user();
        $this->access->assertCanManageUser($actor, $user);
        $user->load(['roles.permissions', 'permissions']);

        $oldRoleIds = $user->roles->pluck('id')->all();
        $oldPermissionIds = $user->permissions->pluck('id')->all();
        $oldPrimary = $user->role_id;

        $roleIds = array_key_exists('role_ids', $validated)
            ? collect($validated['role_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all()
            : $oldRoleIds;
        // A primary named in the request must be among the roles; the one the
        // user already had is kept only while they still hold it.
        $primary = array_key_exists('primary_role_id', $validated)
            ? $this->resolvePrimary($validated['primary_role_id'], $roleIds)
            : $this->resolvePrimary(in_array((int) $user->role_id, $roleIds, true) ? $user->role_id : null, $roleIds);
        $permissionIds = array_key_exists('permission_ids', $validated)
            ? collect($validated['permission_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all()
            : $oldPermissionIds;

        // Only additions can escalate; what is being taken away needs no check
        // beyond the lock-out guards.
        $addedRoles = Role::with('permissions:id')->whereIn('id', array_diff($roleIds, $oldRoleIds))->get();
        $removedRoles = Role::whereIn('id', array_diff($oldRoleIds, $roleIds))->get();
        $this->access->assertCanAssignRoles($actor, $addedRoles);
        foreach ($removedRoles as $role) {
            $this->access->assertCanManageRole($actor, $role);
        }
        $this->access->assertCanGrantPermissions($actor, array_diff($permissionIds, $oldPermissionIds));
        $this->access->assertRoleChangeIsSafe($actor, $user, $roleIds);

        DB::transaction(function () use ($user, $roleIds, $primary, $permissionIds) {
            $user->syncRoles($roleIds);
            $user->syncPermissions($permissionIds);
            if ((int) $user->role_id !== (int) $primary) {
                // syncRoles has already set the pivot exactly. The saved hook
                // would detach the old primary, which may still be among the
                // chosen roles, so write the column without it.
                $user->forceFill(['role_id' => $primary])->saveQuietly();
            }
        });

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: User::class,
            entityId: $user->id,
            description: "تحديث صلاحيات الحساب {$user->email}",
            oldValues: ['role_ids' => $oldRoleIds, 'primary_role_id' => $oldPrimary, 'permission_ids' => $oldPermissionIds],
            newValues: ['role_ids' => $roleIds, 'primary_role_id' => $primary, 'permission_ids' => $permissionIds],
            module: AuditLog::MODULE_USERS,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث صلاحيات المستخدم',
            'data' => $this->present($user->fresh(['roles', 'role', 'permissions']), $actor),
        ]);
    }

    /**
     * Add roles to, or take them from, several accounts at once. Accounts the
     * actor may not change are skipped and reported, not fatal to the rest.
     */
    public function bulkRoles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:200',
            'user_ids.*' => 'integer|exists:users,id',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'integer|exists:roles,id',
            'mode' => 'required|in:add,remove',
        ]);

        $actor = $request->user();
        $roles = Role::with('permissions:id')->whereIn('id', $validated['role_ids'])->get();

        // Handing these roles out at all is checked once, up front.
        if ($validated['mode'] === 'add') {
            $this->access->assertCanAssignRoles($actor, $roles);
        } else {
            $roles->each(fn (Role $role) => $this->access->assertCanManageRole($actor, $role));
        }

        $changed = 0;
        $skipped = [];
        foreach (User::with('roles')->whereIn('id', $validated['user_ids'])->get() as $user) {
            try {
                if ($validated['mode'] === 'add') {
                    $did = (bool) $this->assignments->addRoles($actor, $user, $roles);
                } else {
                    $did = false;
                    foreach ($roles as $role) {
                        $did = $this->assignments->removeRole($actor, $user->fresh(), $role) || $did;
                    }
                }
                $changed += $did ? 1 : 0;
            } catch (HttpException $e) {
                $skipped[] = ['id' => $user->id, 'name' => $user->name, 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "تم تحديث {$changed} حساب".($skipped ? '، وتُخطّي '.count($skipped) : ''),
            'data' => ['changed' => $changed, 'skipped' => $skipped],
        ]);
    }

    /**
     * The primary role has to be one the user holds. With none given, the
     * first held role takes the place; with no roles there is no primary.
     *
     * @param  array<int, int>  $roleIds
     */
    private function resolvePrimary(?int $primary, array $roleIds): ?int
    {
        if (! $roleIds) {
            return null;
        }
        if ($primary && ! in_array((int) $primary, $roleIds, true)) {
            abort(422, 'الدور الأساسي يجب أن يكون من الأدوار المختارة.');
        }

        return $primary ? (int) $primary : $roleIds[0];
    }

    /** @return array<string, mixed> */
    private function present(User $user, ?User $actor): array
    {
        $editable = true;
        try {
            $actor && $this->access->assertCanManageUser($actor, $user);
        } catch (HttpException) {
            $editable = false;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_admin_flag' => (bool) $user->is_admin,
            'is_super' => $user->isSuperAdmin(),
            'is_master' => $user->isMaster(),
            'is_self' => $actor?->is($user) ?? false,
            'editable' => $editable,
            'primary_role_id' => $user->role_id,
            'roles' => $user->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?: $role->name,
                'color' => $role->color,
                'is_active' => (bool) $role->is_active,
            ])->values(),
            'permission_ids' => $user->permissions->pluck('id')->values(),
            'created_at' => $user->created_at?->toDateString(),
        ];
    }
}
