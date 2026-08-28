<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;

class NotificationService
{
    public static function notifyAdminsAndManagers(string $title, string $message, ?string $url = null): void
    {
        try {
            $users = User::role(['Super Admin', 'Manager'])->get();
            foreach ($users as $user) {
                $user->notify(new SystemNotification($title, $message, $url));
            }
        } catch (\Throwable $e) {
            // Log error or fail gracefully
        }
    }
}
