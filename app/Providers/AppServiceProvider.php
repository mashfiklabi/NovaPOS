<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Define gate authorization for permissions
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('Admin')) {
                return true;
            }
        });

        Gate::define('view_dashboard', function (User $user) {
            return $user->hasPermission('view_dashboard');
        });

        Gate::define('manage_users', function (User $user) {
            return $user->hasPermission('manage_users');
        });

        Gate::define('manage_roles', function (User $user) {
            return $user->hasPermission('manage_roles');
        });

        Gate::define('manage_settings', function (User $user) {
            return $user->hasPermission('manage_settings');
        });

        Gate::define('view_audit_logs', function (User $user) {
            return $user->hasPermission('view_audit_logs');
        });
    }
}
