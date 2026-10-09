<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if (auth()->check()) {
            app(\App\Services\AuditService::class)->log(
                action: \App\Models\AuditLog::ACTION_LOGIN,
                entityType: \App\Models\User::class,
                entityId: auth()->id(),
                description: 'تسجيل دخول ويب ناجح للمستخدم: ' . auth()->user()->name,
                module: \App\Models\AuditLog::MODULE_SECURITY,
                userId: auth()->id()
            );
        }

        if (auth()->user()->is_admin) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended('/');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if (auth()->check()) {
            app(\App\Services\AuditService::class)->log(
                action: \App\Models\AuditLog::ACTION_LOGOUT,
                entityType: \App\Models\User::class,
                entityId: auth()->id(),
                description: 'تسجيل خروج ويب للمستخدم: ' . auth()->user()->name,
                module: \App\Models\AuditLog::MODULE_SECURITY,
                userId: auth()->id()
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
