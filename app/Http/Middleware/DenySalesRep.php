<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes order processing and purchasing to sales reps (User::isSalesRep).
 * They raise sales orders; confirming, moving and sourcing them, and buying
 * stock for them, belong to someone else.
 */
class DenySalesRep
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSalesRep()) {
            abort(403, 'هذا الإجراء خارج صلاحيات موظف المبيعات.');
        }

        return $next($request);
    }
}
