<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->status !== UserStatus::ACTIVE) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = 'Your account has been suspended. Please contact the administrator.';
                if ($user->status === UserStatus::INACTIVE) {
                    $message = 'Your account is inactive. Please contact the administrator.';
                }

                return redirect()->route('login')->withErrors([
                    'email' => $message,
                ]);
            }
        }

        return $next($request);
    }
}
