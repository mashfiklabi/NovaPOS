<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\NavigationService;
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

        // Get global settings to share with frontend safely
        $settings = Schema::hasTable('settings') ? Setting::pluck('value', 'key')->toArray() : [];

        // Dynamically get the authorized navigation using the NavigationService
        $navigation = [];
        if ($user) {
            $navigation = (new NavigationService)->getNavigation();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'avatar' => $user->avatar,
                    'phone' => $user->phone,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                ] : null,
            ],
            'navigation' => $navigation,
            'settings' => [
                'shop_name' => $settings['shop_name'] ?? 'NovaPOS',
                'currency' => $settings['currency'] ?? 'USD',
                'tax_rate' => $settings['tax_rate'] ?? '0',
                'phone' => $settings['phone'] ?? '',
                'email' => $settings['email'] ?? '',
                'address' => $settings['address'] ?? '',
                'invoice_prefix' => $settings['invoice_prefix'] ?? 'INV-',
                'timezone' => $settings['timezone'] ?? 'UTC',
                'logo' => $settings['logo'] ?? null,
                'favicon' => $settings['favicon'] ?? null,
            ],
        ];
    }
}
