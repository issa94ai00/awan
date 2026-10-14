<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Feature;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feature management for the master (super admin) role.
 *
 * A "feature" is an ERP module — sales, purchases, accounting, WMS, ... It can
 * be switched off for everyone, and each role can be granted or denied access
 * to it individually. The routes that reach this controller carry the
 * `role:admin` gate, and `isAdmin()` also accepts the master role.
 */
class FeatureController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    /**
     * Everything the management screen needs in one response: the modules, the
     * access matrix and the roles to draw the columns.
     */
    public function index(): JsonResponse
    {
        $features = Feature::with('roles:id,name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Feature $feature) => [
                'id' => $feature->id,
                'key' => $feature->key,
                'name_ar' => $feature->name_ar,
                'name_en' => $feature->name_en,
                'description_ar' => $feature->description_ar,
                'description_en' => $feature->description_en,
                'icon' => $feature->icon,
                'group' => $feature->group,
                'is_enabled' => $feature->is_enabled,
                'is_core' => $feature->is_core,
                'sort_order' => $feature->sort_order,
                'role_ids' => $feature->roles->pluck('id')->values(),
                'paths' => Feature::pathsForKey($feature->key),
            ]);

        $roles = Role::query()
            ->withCount('users')
            ->orderByRaw("FIELD(name, 'master', 'admin') DESC")
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'is_active' => $role->is_active,
                'users_count' => $role->users_count,
                'is_super' => in_array($role->name, ['admin', 'master'], true),
            ]);

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'features' => $features,
                'roles' => $roles,
                'stats' => [
                    'total' => $features->count(),
                    'enabled' => $features->where('is_enabled', true)->count(),
                    'disabled' => $features->where('is_enabled', false)->count(),
                    'groups' => $features->pluck('group')->unique()->count(),
                ],
            ],
        ]);
    }

    /**
     * Switch one feature on or off for the whole company.
     */
    public function update(Request $request, Feature $feature): JsonResponse
    {
        $validated = $request->validate([
            'is_enabled' => 'sometimes|boolean',
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'sometimes|string|max:255',
            'description_ar' => 'nullable|string|max:2000',
            'description_en' => 'nullable|string|max:2000',
        ]);

        // Core modules (the management screens themselves) can never be turned
        // off, or the owner could lock everyone out of the tool that re-enables
        // things.
        if (array_key_exists('is_enabled', $validated) && $feature->is_core && ! $validated['is_enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعطيل هذه الميزة الأساسية.',
                'data' => null,
            ], 422);
        }

        $old = $feature->only(['is_enabled', 'name_ar', 'name_en']);
        $feature->update($validated);

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: Feature::class,
            entityId: $feature->id,
            description: "تحديث الميزة: {$feature->key}",
            oldValues: $old,
            newValues: $feature->only(['is_enabled', 'name_ar', 'name_en']),
            module: AuditLog::MODULE_SETTINGS,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الميزة بنجاح',
            'data' => ['feature' => $feature->fresh()],
        ]);
    }

    /**
     * Replace the set of roles that may access a feature.
     */
    public function updateRoles(Request $request, Feature $feature): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => 'present|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $roleIds = collect($validated['role_ids'])->unique()->values();

        // The master and admin roles bypass the matrix entirely, so storing a
        // grant for them is meaningless and would only look like it can be
        // revoked. Drop them and keep the pivot honest.
        $superRoleIds = Role::whereIn('name', ['admin', 'master'])->pluck('id');
        $roleIds = $roleIds->diff($superRoleIds)->values();

        $old = $feature->roles()->pluck('roles.id')->all();
        $feature->roles()->sync($roleIds->all());

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: Feature::class,
            entityId: $feature->id,
            description: "تحديث صلاحيات الأدوار للميزة: {$feature->key}",
            oldValues: ['role_ids' => $old],
            newValues: ['role_ids' => $roleIds->all()],
            module: AuditLog::MODULE_SETTINGS,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث صلاحيات الميزة بنجاح',
            'data' => ['feature' => $feature->fresh('roles:id,name')],
        ]);
    }

    /**
     * Switch several features on or off at once (bulk actions on the grid).
     */
    public function bulkToggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keys' => 'required|array',
            'keys.*' => 'string|exists:features,key',
            'is_enabled' => 'required|boolean',
        ]);

        $query = Feature::whereIn('key', $validated['keys']);

        if (! $validated['is_enabled']) {
            // Never let a bulk "disable" take the core screens down with it.
            $query->where('is_core', false);
        }

        $query->update(['is_enabled' => $validated['is_enabled']]);

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: Feature::class,
            description: 'تحديث جماعي للميزات: ' . implode(', ', $validated['keys']),
            newValues: ['is_enabled' => $validated['is_enabled'], 'keys' => $validated['keys']],
            module: AuditLog::MODULE_SETTINGS,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الميزات بنجاح',
            'data' => null,
        ]);
    }

    /**
     * Switch all non-core features in a group on or off.
     */
    public function toggleGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => 'required|string',
            'is_enabled' => 'required|boolean',
        ]);

        $query = Feature::where('group', $validated['group']);
        if (! $validated['is_enabled']) {
            $query->where('is_core', false);
        }

        $query->update(['is_enabled' => $validated['is_enabled']]);

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: Feature::class,
            description: "تحديث ميزات المجموعة: {$validated['group']}",
            newValues: ['group' => $validated['group'], 'is_enabled' => $validated['is_enabled']],
            module: AuditLog::MODULE_SETTINGS,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث ميزات المجموعة بنجاح',
            'data' => null,
        ]);
    }

    /**
     * Quick toggle a single role on or off for a feature (for matrix view).
     */
    public function toggleRole(Request $request, Feature $feature): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'granted' => 'required|boolean',
        ]);

        $role = Role::findOrFail($validated['role_id']);

        if (in_array($role->name, ['admin', 'master'], true)) {
            return response()->json([
                'success' => true,
                'message' => 'أدوار الإدارة تملك الصلاحيات دائماً.',
                'data' => ['feature' => $feature->fresh('roles:id,name')],
            ]);
        }

        if ($validated['granted']) {
            $feature->roles()->syncWithoutDetaching([$role->id]);
        } else {
            $feature->roles()->detach($role->id);
        }

        $this->audit->log(
            action: AuditLog::ACTION_UPDATE,
            entityType: Feature::class,
            entityId: $feature->id,
            description: "تحديث صلاحية الدور [{$role->name}] للميزة: {$feature->key}",
            newValues: ['role_id' => $role->id, 'granted' => $validated['granted']],
            module: AuditLog::MODULE_SETTINGS,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث صلاحية الدور بنجاح',
            'data' => [
                'feature_id' => $feature->id,
                'role_id' => $role->id,
                'granted' => (bool) $validated['granted'],
                'role_ids' => $feature->roles()->pluck('roles.id')->values(),
            ],
        ]);
    }
}
