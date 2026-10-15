<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes company-wide figures to sales reps (User::isSalesScoped). Their
 * dashboard and reports are built from their own business instead.
 */
class DenySalesScoped
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSalesScoped()) {
            abort(403, 'هذه البيانات على مستوى الشركة غير متاحة لحساب المبيعات.');
        }

        return $next($request);
    }
}
