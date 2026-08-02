<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the main POS dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view_dashboard');

        // Professional POS metrics & chart data (Dummy/static structured stats as requested)
        return Inertia::render('Dashboard', [
            'metrics' => [
                'today_sales' => 12450.75,
                'today_purchases' => 4120.50,
                'total_products' => 1420,
                'total_customers' => 842,
                'total_suppliers' => 48,
            ],
            'low_stock_alerts' => [
                ['id' => 1, 'name' => 'Wireless Mouse Pro', 'sku' => 'MS-WPRO-01', 'stock' => 3, 'min_stock' => 10],
                ['id' => 2, 'name' => 'USB-C Charging Cable 2m', 'sku' => 'CB-USBC-2M', 'stock' => 5, 'min_stock' => 15],
                ['id' => 3, 'name' => 'Mechanical Keyboard RGB', 'sku' => 'KB-MECH-RGB', 'stock' => 2, 'min_stock' => 5],
            ],
            'recent_sales' => [
                ['id' => 1, 'invoice_no' => 'INV-2026-0001', 'customer' => 'Alice Smith', 'items' => 3, 'total' => 125.50, 'status' => 'Paid', 'time' => '10 mins ago'],
                ['id' => 2, 'invoice_no' => 'INV-2026-0002', 'customer' => 'Bob Johnson', 'items' => 1, 'total' => 45.00, 'status' => 'Paid', 'time' => '25 mins ago'],
                ['id' => 3, 'invoice_no' => 'INV-2026-0003', 'customer' => 'Charlie Brown', 'items' => 5, 'total' => 389.90, 'status' => 'Pending', 'time' => '1 hour ago'],
                ['id' => 4, 'invoice_no' => 'INV-2026-0004', 'customer' => 'David Lee', 'items' => 2, 'total' => 85.00, 'status' => 'Paid', 'time' => '2 hours ago'],
            ],
            'sales_chart_data' => [
                'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'sales' => [1200, 1900, 3000, 5000, 2300, 3400, 12450],
                'purchases' => [800, 1500, 2000, 3100, 1800, 2100, 4120],
            ]
        ]);
    }
}
