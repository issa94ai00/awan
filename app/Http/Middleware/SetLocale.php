<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->query('lang') 
            ?? Session::get('locale') 
            ?? $request->cookie('locale') 
            ?? config('app.locale', 'ar');

        // Validate locale
        if (!in_array($locale, ['en', 'ar'])) {
            $locale = 'ar';
        }

        App::setLocale($locale);
        Session::put('locale', $locale);

        // Set RTL/LTR direction based on locale
        if ($locale === 'ar') {
            config(['app.direction' => 'rtl']);
        } else {
            config(['app.direction' => 'ltr']);
        }

        return $next($request)->withCookie(cookie()->forever('locale', $locale));
    }
}
