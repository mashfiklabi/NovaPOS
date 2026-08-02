<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class SettingService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Get a setting by key.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Get all settings as key-value pairs.
     *
     * @return array<string, string|null>
     */
    public function getAll(): array
    {
        return Setting::pluck('value', 'key')->toArray();
    }

    /**
     * Set multiple settings.
     *
     * @param array<string, string|null> $settings
     */
    public function setMany(array $settings): void
    {
        DB::transaction(function () use ($settings) {
            foreach ($settings as $key => $value) {
                // Ignore tokens or files like logos/favicons that are handled separately, or store string path
                $setting = Setting::firstOrCreate(['key' => $key]);

                $oldValue = $setting->value;

                if ($oldValue !== $value) {
                    $setting->value = $value;
                    $setting->save();

                    $this->auditLogService->log(
                        action: 'setting_updated',
                        modelType: Setting::class,
                        modelId: $setting->id,
                        oldValues: ['key' => $key, 'value' => $oldValue],
                        newValues: ['key' => $key, 'value' => $value]
                    );
                }
            }
        });
    }
}
