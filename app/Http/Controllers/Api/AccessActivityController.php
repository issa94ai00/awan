<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The access-control history: who changed which role, permission or account's
 * access, and when — read from the audit log the access controllers write.
 *
 * Narrowed to one role (its own edits plus the members added to or taken from
 * it) or one account. Each scope needs the permission of the screen it serves.
 */
class AccessActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $actor = $request->user();
        $roleId = $validated['role_id'] ?? null;
        $userId = $validated['user_id'] ?? null;

        if ($roleId && ! $actor->hasPermission('roles.view')) {
            abort(403, 'ليس لديك صلاحية لعرض سجل الأدوار.');
        }
        if ($userId && ! $actor->hasPermission('users.view')) {
            abort(403, 'ليس لديك صلاحية لعرض سجل المستخدمين.');
        }

        $query = AuditLog::query()
            ->with('user:id,name')
            ->whereIn('module', [AuditLog::MODULE_SECURITY, AuditLog::MODULE_USERS])
            ->latest('id');

        if ($roleId) {
            $query->where(fn ($q) => $q
                ->where(fn ($r) => $r->where('entity_type', Role::class)->where('entity_id', $roleId))
                ->orWhere(fn ($u) => $u->where('entity_type', User::class)->whereJsonContains('metadata->role_ids', (int) $roleId)));
        } elseif ($userId) {
            $query->where('entity_type', User::class)->where('entity_id', $userId);
        } else {
            $query->whereIn('entity_type', [Role::class, Permission::class, User::class]);
        }

        $page = $query->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'entries' => collect($page->items())->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'entity' => class_basename((string) $log->entity_type),
                    'entity_id' => $log->entity_id,
                    'description' => $log->description,
                    'actor' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                    'added' => count($log->metadata['added'] ?? $log->metadata['added_role_ids'] ?? []),
                    'removed' => count($log->metadata['removed'] ?? $log->metadata['removed_role_ids'] ?? []),
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->values(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }
}
