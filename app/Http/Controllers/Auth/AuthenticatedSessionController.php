<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user) {
            // Update last login timestamp
            $user->last_login_at = now();
            $user->save();

            // Log Spatie Activity Log with IP & Browser properties
            activity()
                ->performedOn($user)
                ->event('login')
                ->withProperties([
                    'ip' => $request->ip(),
                    'browser' => $request->userAgent(),
                ])
                ->log("User logged in: {$user->name}");
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            activity()
                ->performedOn($user)
                ->event('logout')
                ->withProperties([
                    'ip' => $request->ip(),
                    'browser' => $request->userAgent(),
                ])
                ->log("User logged out: {$user->name}");
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
