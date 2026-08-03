<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Str;

class UserObserver
{
    /**
     * Handle the User "creating" event.
     *
     * AUDIT TRAIL JUSTIFICATION:
     * We retain 'created_by' to track which system administrator or manager created the user account.
     * In retail environments, this is vital for audit compliance, fraud prevention, and associate tracking.
     */
    public function creating(User $user): void
    {
        if (empty($user->uuid)) {
            $user->uuid = (string) Str::uuid();
        }

        if (auth()->check()) {
            $user->created_by = auth()->id();
        }
    }

    /**
     * Handle the User "updating" event.
     *
     * AUDIT TRAIL JUSTIFICATION:
     * We retain 'updated_by' to record who authorized the latest profile adjustments or account suspension.
     * This provides transparent accountability across shifts and administrative tasks.
     */
    public function updating(User $user): void
    {
        if (auth()->check()) {
            $user->updated_by = auth()->id();
        }
    }

    /**
     * Handle the User "deleting" event.
     *
     * AUDIT TRAIL JUSTIFICATION:
     * We retain 'deleted_by' to track who soft-deleted or archived the user's account profile,
     * maintaining operational safety audits.
     */
    public function deleting(User $user): void
    {
        if (auth()->check() && $user->isForceDeleting() === false) {
            $user->deleted_by = auth()->id();
            $user->saveQuietly();
        }
    }
}
