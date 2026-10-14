<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to the `master` role. Unlike `role:`, an `is_admin` account
 * or the built-in `admin` role does not pass on its own.
 */
class EnsureMaster
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isMaster()) {
            abort(403, 'ليس لديك صلاحية للوصول إلى هذا القسم.');
        }

        return $next($request);
    }
}
