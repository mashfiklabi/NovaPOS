<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Welcome Page
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

// Protected Application Routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard Module
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Users Management Module
    Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);

    // Categories Module
    Route::get('categories/export', [CategoryController::class, 'export'])->name('categories.export');
    Route::post('categories/bulk-delete', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-delete');
    Route::post('categories/bulk-restore', [CategoryController::class, 'bulkRestore'])->name('categories.bulk-restore');
    Route::post('categories/{id}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
    Route::resource('categories', CategoryController::class)->except(['create', 'edit', 'show']);

    // Brands Module
    Route::get('brands/export', [BrandController::class, 'export'])->name('brands.export');
    Route::post('brands/bulk-delete', [BrandController::class, 'bulkDestroy'])->name('brands.bulk-delete');
    Route::post('brands/bulk-restore', [BrandController::class, 'bulkRestore'])->name('brands.bulk-restore');
    Route::post('brands/{id}/restore', [BrandController::class, 'restore'])->name('brands.restore');
    Route::resource('brands', BrandController::class)->except(['create', 'edit', 'show']);

    // Units Module
    Route::get('units/export', [UnitController::class, 'export'])->name('units.export');
    Route::post('units/bulk-delete', [UnitController::class, 'bulkDestroy'])->name('units.bulk-delete');
    Route::post('units/bulk-restore', [UnitController::class, 'bulkRestore'])->name('units.bulk-restore');
    Route::post('units/{id}/restore', [UnitController::class, 'restore'])->name('units.restore');
    Route::resource('units', UnitController::class)->except(['create', 'edit', 'show']);

    // Products Module
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::post('products/bulk-delete', [ProductController::class, 'bulkDestroy'])->name('products.bulk-delete');
    Route::post('products/bulk-restore', [ProductController::class, 'bulkRestore'])->name('products.bulk-restore');
    Route::post('products/{id}/restore', [ProductController::class, 'restore'])->name('products.restore');
    Route::resource('products', ProductController::class)->except(['create', 'edit', 'show']);

    // Suppliers Module
    Route::get('suppliers/export', [SupplierController::class, 'export'])->name('suppliers.export');
    Route::post('suppliers/bulk-delete', [SupplierController::class, 'bulkDestroy'])->name('suppliers.bulk-delete');
    Route::post('suppliers/bulk-restore', [SupplierController::class, 'bulkRestore'])->name('suppliers.bulk-restore');
    Route::post('suppliers/{id}/restore', [SupplierController::class, 'restore'])->name('suppliers.restore');
    Route::resource('suppliers', SupplierController::class)->except(['create', 'edit', 'show']);

    // Purchases Module
    Route::get('purchases/export', [PurchaseController::class, 'export'])->name('purchases.export');
    Route::post('purchases/bulk-delete', [PurchaseController::class, 'bulkDestroy'])->name('purchases.bulk-delete');
    Route::post('purchases/bulk-restore', [PurchaseController::class, 'bulkRestore'])->name('purchases.bulk-restore');
    Route::post('purchases/{id}/restore', [PurchaseController::class, 'restore'])->name('purchases.restore');
    Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
    Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
    Route::post('purchases/{purchase}/pay', [PurchaseController::class, 'pay'])->name('purchases.pay');
    Route::resource('purchases', PurchaseController::class);

    // Customers Module
    Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::post('customers/bulk-delete', [CustomerController::class, 'bulkDestroy'])->name('customers.bulk-delete');
    Route::post('customers/bulk-restore', [CustomerController::class, 'bulkRestore'])->name('customers.bulk-restore');
    Route::post('customers/{id}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
    Route::resource('customers', CustomerController::class)->except(['create', 'edit', 'show']);

    // Sales & POS Module
    Route::get('sales/export', [SaleController::class, 'export'])->name('sales.export');
    Route::post('sales/bulk-delete', [SaleController::class, 'bulkDestroy'])->name('sales.bulk-delete');
    Route::post('sales/bulk-restore', [SaleController::class, 'bulkRestore'])->name('sales.bulk-restore');
    Route::post('sales/{id}/restore', [SaleController::class, 'restore'])->name('sales.restore');
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::post('sales/{sale}/pay', [SaleController::class, 'pay'])->name('sales.pay');
    Route::post('sales/{sale}/refund', [SaleController::class, 'refund'])->name('sales.refund');
    Route::post('sales/{sale}/credit', [SaleController::class, 'credit'])->name('sales.credit');
    Route::resource('sales', SaleController::class);

    // Roles & Permissions Module
    Route::resource('roles', RoleController::class)->except(['create', 'edit', 'show']);

    // Notifications Module
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    // System Settings Module
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Activity Logs Module
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    // User Profile Module
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
