<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Seed Granular Permissions
        $permissions = [
            'dashboard.view' => 'Ability to view the main dashboard metrics and charts',

            'settings.view' => 'Ability to view settings config',
            'settings.update' => 'Ability to edit global system and shop configuration settings',

            'users.view' => 'Ability to view users index list',
            'users.create' => 'Ability to register new system users',
            'users.update' => 'Ability to edit existing system users',
            'users.delete' => 'Ability to soft-delete or block system users',

            'roles.view' => 'Ability to view roles index list',
            'roles.create' => 'Ability to design new user roles',
            'roles.update' => 'Ability to edit existing user roles and mapping',
            'roles.delete' => 'Ability to remove user roles',

            'permissions.view' => 'Ability to list system-level permissions',
        ];

        foreach ($permissions as $name => $description) {
            Permission::findOrCreate($name, 'web');
        }

        // 2. Seed Super Admin Role
        $superAdminRole = Role::findOrCreate('Super Admin', 'web');

        // Super Admin gets all permissions assigned
        $superAdminRole->syncPermissions(array_keys($permissions));

        // Let's create some other roles for demo purposes (e.g. Manager, Cashier)
        $managerRole = Role::findOrCreate('Manager', 'web');
        $managerRole->syncPermissions([
            'dashboard.view',
            'users.view',
            'users.create',
            'users.update',
        ]);

        $cashierRole = Role::findOrCreate('Cashier', 'web');
        $cashierRole->syncPermissions([
            'dashboard.view',
        ]);

        // 3. Seed Default Super Admin User
        $admin = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => 'admin@novapos.com',
            'password' => 'Password123!', // Automatically hashed by User cast
            'phone' => '+1 (555) 019-0000',
            'status' => UserStatus::ACTIVE,
        ]);

        // Assign Spatie Super Admin role
        $admin->assignRole($superAdminRole);

        // Seed some demo users
        $managerUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Jane Manager',
            'email' => 'manager@novapos.com',
            'password' => 'Password123!',
            'phone' => '+1 (555) 019-1111',
            'status' => UserStatus::ACTIVE,
        ]);
        $managerUser->assignRole($managerRole);

        $cashierUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'John Cashier',
            'email' => 'cashier@novapos.com',
            'password' => 'Password123!',
            'phone' => '+1 (555) 019-2222',
            'status' => UserStatus::ACTIVE,
        ]);
        $cashierUser->assignRole($cashierRole);

        // 4. Seed Settings Table
        $settings = [
            [
                'key' => 'shop_name',
                'value' => 'NovaPOS Retail',
                'group' => 'general',
                'type' => 'text',
            ],
            [
                'key' => 'phone',
                'value' => '+1 (555) 019-2834',
                'group' => 'general',
                'type' => 'text',
            ],
            [
                'key' => 'email',
                'value' => 'info@novapos.com',
                'group' => 'general',
                'type' => 'text',
            ],
            [
                'key' => 'address',
                'value' => '123 Tech Avenue, Silicon Valley, CA',
                'group' => 'general',
                'type' => 'textarea',
            ],
            [
                'key' => 'currency',
                'value' => 'USD',
                'group' => 'localization',
                'type' => 'text',
            ],
            [
                'key' => 'timezone',
                'value' => 'UTC',
                'group' => 'localization',
                'type' => 'text',
            ],
            [
                'key' => 'invoice_prefix',
                'value' => 'INV-',
                'group' => 'pos',
                'type' => 'text',
            ],
            [
                'key' => 'tax_rate',
                'value' => '8.25',
                'group' => 'pos',
                'type' => 'number',
            ],
            [
                'key' => 'logo',
                'value' => null,
                'group' => 'appearance',
                'type' => 'file',
            ],
            [
                'key' => 'favicon',
                'value' => null,
                'group' => 'appearance',
                'type' => 'file',
            ],
        ];

        foreach ($settings as $item) {
            Setting::create($item);
        }
    }
}
