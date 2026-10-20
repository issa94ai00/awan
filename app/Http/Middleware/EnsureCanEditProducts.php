<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes product edits and deletions to roles that may only add products and
 * view them (User::mayEditProducts).
 */
class EnsureCanEditProducts
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->mayEditProducts()) {
            abort(403, 'لا يمكنك تعديل المنتجات أو حذفها، يمكنك إضافتها وعرضها فقط.');
        }

        return $next($request);
    }
}
