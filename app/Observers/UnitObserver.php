<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Unit;
use Illuminate\Support\Str;

class UnitObserver
{
    /**
     * Handle the Unit "creating" event.
     */
    public function creating(Unit $unit): void
    {
        if (empty($unit->uuid)) {
            $unit->uuid = (string) Str::uuid();
        }

        if (auth()->check()) {
            $unit->created_by = auth()->id();
        }
    }

    /**
     * Handle the Unit "updating" event.
     */
    public function updating(Unit $unit): void
    {
        if (auth()->check()) {
            $unit->updated_by = auth()->id();
        }
    }

    /**
     * Handle the Unit "deleting" event.
     */
    public function deleting(Unit $unit): void
    {
        if (auth()->check() && method_exists($unit, 'isForceDeleting') && ! $unit->isForceDeleting()) {
            $unit->deleted_by = auth()->id();
            $unit->saveQuietly();
        }
    }
}
