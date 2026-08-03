<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Display the main POS dashboard with active metrics and log history.
     */
    public function index(Request $request): Response
    {
        $this->authorize('dashboard.view');

        // Capture actual counts from the database
        $totalUsers = User::count();
        $totalRoles = Role::count();
        $totalPermissions = Permission::count();
        $totalSettings = Setting::count();

        // Query Spatie activity logs
        $recentActivities = Activity::with('causer')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'user' => $activity->causer ? $activity->causer->name : 'System',
                    'action' => $activity->description,
                    'ip' => $activity->getExtraProperty('ip') ?? '127.0.0.1',
                    'browser' => $activity->getExtraProperty('browser') ?? 'System',
                    'timestamp' => $activity->created_at->toIso8601String(),
                ];
            });

        // Query users with their last_login_at timestamp
        $latestLogins = User::whereNotNull('last_login_at')
            ->orderBy('last_login_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar' => $u->avatar,
                    'last_login_at' => $u->last_login_at ? $u->last_login_at->toIso8601String() : null,
                ];
            });

        // Mock chart stats for checkout flow (sales vs purchases)
        $chartData = [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'sales' => [1200, 1900, 3000, 5000, 2300, 3400, 12450],
            'purchases' => [800, 1500, 2000, 3100, 1800, 2100, 4120],
        ];

        return Inertia::render('Dashboard', [
            'metrics' => [
                'users' => $totalUsers,
                'roles' => $totalRoles,
                'permissions' => $totalPermissions,
                'settings' => $totalSettings,
            ],
            'recent_activities' => $recentActivities,
            'latest_logins' => $latestLogins,
            'chart_data' => $chartData,
        ]);
    }
}
