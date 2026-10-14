<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * `role:accountant` or `role:sells,manager` (any one). Super admins pass.
     * Resolves against every Spatie role the user holds, not just `role_id`.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect()->guest(route('login'));
        }

        $user = auth()->user();

        if ($user->isAdmin()) {
            return $next($request);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        abort(403, 'ليس لديك صلاحية للوصول إلى هذا القسم.');
    }
}
