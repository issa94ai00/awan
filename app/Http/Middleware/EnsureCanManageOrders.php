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
     * Checks role names (Spatie roles, any the user holds) rather than a
     * permission, matching how the rest of the API is gated today.
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
