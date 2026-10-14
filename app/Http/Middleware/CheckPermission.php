<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `permission:products.edit` or `permission:roles.view|roles.edit` (any one).
 *
 * Resolves through User::hasPermission, so super admins pass and inactive
 * roles or permissions grant nothing. API requests get JSON 401/403.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $names = collect($permissions)
            ->flatMap(fn (string $p) => explode('|', $p))
            ->map(fn (string $p) => trim($p))
            ->filter()
            ->values()
            ->all();

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], 401);
            }

            return redirect()->guest(route('login'));
        }

        if ($user->hasAnyPermissionNamed($names)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء',
                'required_permissions' => $names,
            ], 403);
        }

        abort(403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة');
    }
}
