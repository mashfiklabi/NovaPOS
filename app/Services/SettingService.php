<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingService
{
    /**
     * Get a setting by key.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $setting = Setting::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Get all settings mapped by key.
     *
     * @return array<string, string|null>
     */
    public function getAll(): array
    {
        return Setting::pluck('value', 'key')->toArray();
    }

    /**
     * Get settings grouped by their group field.
     *
     * @return array<string, array<Setting>>
     */
    public function getGrouped(): array
    {
        return Setting::all()->groupBy('group')->toArray();
    }

    /**
     * Set multiple settings.
     *
     * @param  array<string, mixed>  $settings
     */
    public function setMany(array $settings): void
    {
        DB::transaction(function () use ($settings) {
            foreach ($settings as $key => $value) {
                // If value is an uploaded file (like Logo or Favicon)
                if ($value instanceof UploadedFile) {
                    $setting = Setting::where('key', $key)->first();
                    if ($setting && $setting->value) {
                        Storage::disk('public')->delete($setting->value);
                    }
                    $value = $value->store('settings', 'public');
                }

                $setting = Setting::where('key', $key)->first();
                if ($setting) {
                    $oldValue = $setting->value;
                    if ($oldValue !== $value) {
                        $setting->value = $value;
                        $setting->save();
                    }
                }
            }
        });
    }
}
