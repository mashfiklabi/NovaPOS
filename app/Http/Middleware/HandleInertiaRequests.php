<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Get global settings to share with frontend safely (checking table existence for tests/initial installations)
        $settings = Schema::hasTable('settings') ? Setting::pluck('value', 'key')->toArray() : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'permissions' => $user->getPermissionNames(),
                ] : null,
            ],
            'settings' => [
                'shop_name' => $settings['shop_name'] ?? 'NovaPOS',
                'currency' => $settings['currency'] ?? 'USD',
                'tax_rate' => $settings['tax_rate'] ?? '0',
                'phone' => $settings['phone'] ?? '',
                'address' => $settings['address'] ?? '',
                'invoice_prefix' => $settings['invoice_prefix'] ?? 'INV-',
                'timezone' => $settings['timezone'] ?? 'UTC',
            ],
        ];
    }
}
