<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class LoadSettings
{
    public function handle(Request $request, Closure $next)
    {
        // Load settings into session if not already loaded
        if (!session()->has('settings_loaded')) {
            try {
                $settings = Setting::all();
                session(['setting' => $settings]);
                session(['settings_loaded' => true]);
            } catch (\Throwable $e) {
                // Silently fail if table doesn't exist yet
            }
        }

        return $next($request);
    }
}
