<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * Display the settings list grouped.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Setting::class);

        // Fetch settings grouped by category
        $groupedSettings = Setting::all()->groupBy('group');

        return Inertia::render('Settings/Index', [
            'settings' => $this->settingService->getAll(),
            'grouped_settings' => $groupedSettings,
        ]);
    }

    /**
     * Update settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:100',
            'email' => 'required|email|max:255',
            'address' => 'nullable|string|max:1000',
            'currency' => 'required|string|in:USD,BDT,EUR,GBP,INR,JPY,CNY,CAD,AUD,CHF,SAR,AED,SGD,MYR,PKR',
            'timezone' => 'required|string|timezone',
            'invoice_prefix' => 'required|string|max:15',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
        ]);

        $this->settingService->setMany($validated);

        return redirect()->back()->with('success', 'System settings saved successfully.');
    }
}
