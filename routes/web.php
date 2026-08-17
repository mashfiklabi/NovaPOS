<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
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
    Route::get('brands/{brand}/logo', [BrandController::class, 'logo'])->name('brands.logo');
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
    Route::get('products/{product}/image', [ProductController::class, 'image'])->name('products.image');
    Route::post('products/bulk-delete', [ProductController::class, 'bulkDestroy'])->name('products.bulk-delete');
    Route::post('products/bulk-restore', [ProductController::class, 'bulkRestore'])->name('products.bulk-restore');
    Route::post('products/{id}/restore', [ProductController::class, 'restore'])->name('products.restore');
    Route::resource('products', ProductController::class)->except(['create', 'edit', 'show']);

    // Roles & Permissions Module
    Route::resource('roles', RoleController::class)->except(['create', 'edit', 'show']);

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
