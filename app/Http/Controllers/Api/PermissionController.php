<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use App\Support\PagePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The permission catalogue (الصلاحيات) and the role × permission matrix.
 *
 * Names follow `module.action`. System permissions — every one the code or the
 * seeders check by name — can be described and switched off but never renamed
 * or deleted, since that would break the checks naming them.
 */
class PermissionController extends Controller
{
    private const NAME_RULE = 'regex:/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_.]*$/';

    public function __construct(private AuditService $audit) {}

    /** The catalogue with, for each permission, which roles hold it. */
    public function index(): JsonResponse
    {
        $directCounts = DB::table('model_has_permissions')
            ->select('permission_id', DB::raw('COUNT(*) as c'))
            ->groupBy('permission_id')
            ->pluck('c', 'permission_id');

        $permissions = Permission::with('roles:id')
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $p) => $this->present($p) + [
                'direct_users_count' => (int) ($directCounts[$p->id] ?? 0),
            ]);

        $roles = Role::query()
            ->withCount('users')
            ->orderByRaw("CASE name WHEN 'master' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
            ->orderBy('display_name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?: $role->name,
                'color' => $role->color,
                'is_active' => (bool) $role->is_active,
                'is_system' => (bool) $role->is_system,
                'is_super' => $role->isSuper(),
                'users_count' => $role->users_count,
            ]);

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'permissions' => $permissions,
                'roles' => $roles,
                'modules' => $permissions->pluck('module')->unique()->values(),
                'stats' => [
                    'total' => $permissions->count(),
                    'active' => $permissions->where('is_active', true)->count(),
                    'modules' => $permissions->pluck('module')->unique()->count(),
                    'unassigned' => $permissions->filter(fn ($p) => $p['role_ids']->isEmpty() && $p['direct_users_count'] === 0)->count(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', self::NAME_RULE, Rule::unique('permissions', 'name')],
            'display_name' => 'required|string|max:150',
            'module' => ['nullable', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ], [
            'name.regex' => 'اسم الصلاحية بصيغة module.action بأحرف إنجليزية صغيرة، مثل products.export',
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'display_name' => $validated['display_name'],
            'module' => $validated['module'] ?? strtok($validated['name'], '.'),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_system' => false,
        ]);

        // Super roles bypass checks anyway; holding the row keeps the matrix
        // and the effective-permissions view honest.
        Role::whereIn('name', Role::SUPER_ROLES)->get()->each->givePermissionTo($permission);

        $this->audit->log(
            action: AuditLog::ACTION_CREATE,
            entityType: Permission::class,
            entityId: $permission->id,
            description: "إنشاء صلاحية: {$permission->name}",
            newValues: $permission->only(['name', 'display_name', 'module']),
            module: AuditLog::MODULE_SECURITY,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الصلاحية',
            'data' => $this->present($permission->load('roles:id')),
        ], 201);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', self::NAME_RULE, Rule::unique('permissions', 'name')->ignore($permission->id)],
            'display_name' => 'sometimes|required|string|max:150',
            'module' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($permission->is_system && isset($validated['name']) && $validated['name'] !== $permission->name) {
            abort(422, 'لا يمكن تغيير اسم صلاحية أساسية؛ الكود يتحقق منها بالاسم.');
        }

        $old = $permission->only(array_keys($validated));
        $permission->update($validated);

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: Permission::class,
            entityId: $permission->id,
            description: "تعديل صلاحية: {$permission->name}",
            oldValues: $old,
            newValues: $validated,
            module: AuditLog::MODULE_SECURITY,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصلاحية',
            'data' => $this->present($permission->load('roles:id')),
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        if ($permission->is_system) {
            abort(422, 'لا يمكن حذف صلاحية أساسية في النظام.');
        }

        $snapshot = $permission->only(['name', 'display_name', 'module']) + [
            'role_ids' => $permission->roles()->pluck('roles.id')->all(),
        ];
        $permission->delete();

        $this->audit->log(
            action: AuditLog::ACTION_DELETE,
            entityType: Permission::class,
            entityId: $permission->id,
            description: "حذف صلاحية: {$snapshot['name']}",
            oldValues: $snapshot,
            module: AuditLog::MODULE_SECURITY,
        );

        return response()->json(['success' => true, 'message' => 'تم حذف الصلاحية', 'data' => null]);
    }

    /** @return array<string, mixed> */
    private function present(Permission $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'display_name' => $p->display_name ?: $p->name,
            'module' => $p->module ?: strtok($p->name, '.'),
            'description' => $p->description,
            'is_active' => (bool) $p->is_active,
            'is_system' => (bool) $p->is_system,
            'page' => PagePermissions::PAGES[$p->name][3] ?? null,
            'role_ids' => $p->roles->pluck('id')->values(),
        ];
    }
}
