<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class NavigationService
{
    /**
     * Get the authorized navigation items for the currently logged-in user.
     *
     * @return array<array{title: string, route: string, icon: string, permission: string}>
     */
    public function getNavigation(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        $items = config('navigation', []);

        // Super Admin gets wildcard access to everything
        if ($user->hasRole('Super Admin')) {
            return $items;
        }

        // Cache loaded permission names to avoid N+1 queries during filter
        static $seededPermissions = null;
        if ($seededPermissions === null) {
            try {
                $seededPermissions = Permission::pluck('name')->toArray();
            } catch (\Exception $e) {
                $seededPermissions = [];
            }
        }

        return array_values(array_filter($items, function (array $item) use ($user, $seededPermissions) {
            // Defensive check: If Spatie permission is not seeded yet, safely hide item to prevent crashes
            if (! in_array($item['permission'], $seededPermissions, true)) {
                return false;
            }

            return $user->hasPermissionTo($item['permission']);
        }));
    }
}
