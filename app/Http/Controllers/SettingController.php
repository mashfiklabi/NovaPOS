<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingUpdateRequest;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * Display the settings list.
     */
    public function index(): Response
    {
        $this->authorize('manage_settings');

        return Inertia::render('Settings/Index', [
            'settings' => $this->settingService->getAll(),
        ]);
    }

    /**
     * Update settings.
     */
    public function update(SettingUpdateRequest $request): RedirectResponse
    {
        // Validation occurs automatically via SettingUpdateRequest
        $validated = $request->validated();

        $this->settingService->setMany($validated);

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}
