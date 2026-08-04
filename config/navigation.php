<?php

declare(strict_types=1);

return [
    [
        'title' => 'Dashboard',
        'route' => 'dashboard',
        'icon' => 'HomeIcon',
        'permission' => 'dashboard.view',
    ],
    [
        'title' => 'Users',
        'route' => 'users.index',
        'icon' => 'UsersIcon',
        'permission' => 'users.view',
    ],
    [
        'title' => 'Roles & Permissions',
        'route' => 'roles.index',
        'icon' => 'KeyIcon',
        'permission' => 'roles.view',
    ],
    [
        'title' => 'Settings',
        'route' => 'settings.index',
        'icon' => 'Cog6ToothIcon',
        'permission' => 'settings.view',
    ],
    [
        'title' => 'Activity Logs',
        'route' => 'activity-logs.index',
        'icon' => 'ClipboardDocumentIcon',
        'permission' => 'settings.view',
    ],
];
