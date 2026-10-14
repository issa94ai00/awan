<?php

namespace App\Http\Middleware;

use App\Models\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a request when the ERP module it belongs to has been switched off or
 * the signed-in user's role has not been granted that module. The module is
 * derived from the request path by App\Models\Feature::resolveKeysForPath, so a
 * single middleware covers every module route instead of tagging each group.
 *
 * Paths that map to no module (auth, uploads, the field app, ...) pass through.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // Route through /api/v1 once; strip the version segment if present.
        if (str_starts_with($path, 'api/v1/')) {
            $path = substr($path, strlen('api/v1/'));
        } elseif (str_starts_with($path, 'api/')) {
            $path = substr($path, strlen('api/'));
        }

        $keys = Feature::resolveKeysForPath($path);

        if ($keys === []) {
            return $next($request);
        }

        $user = $request->user() ?: auth()->user();

        if (! $user) {
            return $next($request);
        }

        if (! collect($keys)->contains(fn (string $key) => Feature::accessible($key, $user))) {
            abort(403, 'هذه الميزة غير متاحة لدورك الحالي.');
        }

        return $next($request);
    }
}
