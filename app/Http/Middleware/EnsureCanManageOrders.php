<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageOrders
{
    /**
     * Restricts order-management endpoints to admin/manager/employee roles.
     *
     * Deliberately checks the role name directly (User::hasRole) rather than
     * the generic permission system (Role::hasPermission), which is broken in
     * production — its pivot table `permission_role` does not exist against
     * the live schema (Spatie-style `role_has_permissions`/`model_has_roles`
     * tables exist instead, but spatie/laravel-permission isn't installed and
     * the User model doesn't use its HasRoles trait).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $allowed = $user->isAdmin() || $user->hasRole('manager') || $user->hasRole('employee');

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لإدارة الطلبيات',
            ], 403);
        }

        return $next($request);
    }
}
