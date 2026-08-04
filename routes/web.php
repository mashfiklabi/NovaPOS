<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
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
