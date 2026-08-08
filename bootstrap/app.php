<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\LoadSettings;
use App\Http\Middleware\TrackVisitors;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureCanManageOrders;
use App\Http\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => IsAdmin::class,
            'permission' => CheckPermission::class,
            'manage_orders' => EnsureCanManageOrders::class,
        ]);
        $middleware->web(append: [
            SetLocale::class,
            LoadSettings::class,
            TrackVisitors::class,
        ]);
        // The API routes sit inside a group that applies the `web` stack (for
        // SetLocale, LoadSettings and the session the cart needs), which drags
        // CSRF verification along with it. Every write from the admin SPA then
        // failed with "CSRF token mismatch", because it authenticates with a
        // Sanctum bearer token and carries no CSRF token.
        //
        // Exempting the API is the right boundary: CSRF defends cookie-based
        // requests the browser attaches automatically, whereas a bearer token
        // has to be read from storage and set deliberately, which a cross-site
        // attacker cannot do.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
