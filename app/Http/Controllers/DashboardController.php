<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Display the main NovaPOS admin dashboard with metrics, alerts, and trend charts.
     */
    public function index(Request $request): Response
    {
        $this->authorize('dashboard.view');

        $user = Auth::user();
        $isSuperAdmin = $user && $user->hasRole('Super Admin');

        // RBAC Permissions check using $user->can() to safely handle missing permission rows in test environments
        $canViewProducts = $isSuperAdmin || ($user && $user->can('products.view'));
        $canViewPurchases = $isSuperAdmin || ($user && $user->can('purchases.view'));
        $canViewUsers = $isSuperAdmin || ($user && $user->can('users.view'));

        // Product metrics
        $totalProducts = $canViewProducts ? Product::count() : 0;
        $activeProducts = $canViewProducts ? Product::where('status', 'active')->count() : 0;
        $lowStockCount = $canViewProducts ? Product::where('status', 'active')
            ->where('track_stock', true)
            ->whereRaw('current_stock <= stock_alert_threshold')
            ->count() : 0;
        $outOfStockCount = $canViewProducts ? Product::where('status', 'active')
            ->where('track_stock', true)
            ->where('current_stock', '<=', 0)
            ->count() : 0;

        // Purchase metrics
        $todayPurchasesCount = $canViewPurchases ? Purchase::whereDate('purchase_date', now()->today())->count() : 0;
        $todayPurchasesTotal = $canViewPurchases ? (float) Purchase::whereDate('purchase_date', now()->today())->sum('grand_total') : 0.0;
        $outstandingPurchaseDue = $canViewPurchases ? (float) Purchase::where('payment_status', '!=', PaymentStatus::PAID->value)->sum('due_amount') : 0.0;

        // User and system metrics
        $totalUsers = $canViewUsers ? User::count() : 0;
        $totalRoles = $isSuperAdmin || ($user && $user->can('roles.view')) ? Role::count() : 0;

        // Low stock products alert list (top 5)
        $lowStockProducts = $canViewProducts ? Product::where('status', 'active')
            ->where('track_stock', true)
            ->whereRaw('current_stock <= stock_alert_threshold')
            ->select('id', 'name', 'sku', 'current_stock', 'stock_alert_threshold')
            ->orderBy('current_stock', 'asc')
            ->limit(5)
            ->get() : [];

        // Recent Purchases list (top 5)
        $recentPurchases = $canViewPurchases ? Purchase::with('supplier')
            ->select('id', 'po_number', 'supplier_id', 'purchase_date', 'grand_total', 'due_amount', 'status', 'payment_status')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($purchase) {
                return [
                    'id' => $purchase->id,
                    'po_number' => $purchase->po_number,
                    'supplier' => $purchase->supplier ? $purchase->supplier->name : 'N/A',
                    'purchase_date' => $purchase->purchase_date ? $purchase->purchase_date->format('Y-m-d') : 'N/A',
                    'grand_total' => (float) $purchase->grand_total,
                    'due_amount' => (float) $purchase->due_amount,
                    'status' => $purchase->status->value ?? $purchase->status,
                    'payment_status' => $purchase->payment_status->value ?? $purchase->payment_status,
                ];
            }) : [];

        // Purchase trend chart (last 7 days)
        $chartLabels = [];
        $chartPurchaseTotals = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $chartLabels[] = $date->format('D (M j)');
            if ($canViewPurchases) {
                $dayTotal = Purchase::whereDate('purchase_date', $date->toDateString())->sum('grand_total');
                $chartPurchaseTotals[] = (float) $dayTotal;
            } else {
                $chartPurchaseTotals[] = 0.0;
            }
        }

        // Activity log
        $recentActivities = $isSuperAdmin ? Activity::with('causer')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'user' => $activity->causer ? $activity->causer->name : 'System',
                    'action' => $activity->description,
                    'timestamp' => $activity->created_at->toIso8601String(),
                ];
            }) : [];

        return Inertia::render('Dashboard', [
            'metrics' => [
                'today_purchases_count' => $todayPurchasesCount,
                'today_purchases_total' => $todayPurchasesTotal,
                'outstanding_purchase_due' => $outstandingPurchaseDue,
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'total_users' => $totalUsers,
                'total_roles' => $totalRoles,
            ],
            'low_stock_products' => $lowStockProducts,
            'recent_purchases' => $recentPurchases,
            'chart_data' => [
                'labels' => $chartLabels,
                'purchases' => $chartPurchaseTotals,
            ],
            'recent_activities' => $recentActivities,
            'permissions' => [
                'can_view_products' => $canViewProducts,
                'can_view_purchases' => $canViewPurchases,
                'can_view_users' => $canViewUsers,
            ],
        ]);
    }
}
