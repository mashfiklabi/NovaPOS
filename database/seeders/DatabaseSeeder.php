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

            // Master Data - Categories
            'categories.view' => 'Ability to view categories',
            'categories.create' => 'Ability to create categories',
            'categories.update' => 'Ability to update categories',
            'categories.delete' => 'Ability to delete categories',
            'categories.restore' => 'Ability to restore soft deleted categories',
            'categories.force_delete' => 'Ability to permanently delete categories',
            'categories.bulk_delete' => 'Ability to bulk delete categories',
            'categories.bulk_restore' => 'Ability to bulk restore categories',
            'categories.bulk_force_delete' => 'Ability to bulk permanently delete categories',
            'categories.export' => 'Ability to export categories to CSV',

            // Master Data - Brands
            'brands.view' => 'Ability to view brands',
            'brands.create' => 'Ability to create brands',
            'brands.update' => 'Ability to update brands',
            'brands.delete' => 'Ability to delete brands',
            'brands.restore' => 'Ability to restore soft deleted brands',
            'brands.force_delete' => 'Ability to permanently delete brands',
            'brands.bulk_delete' => 'Ability to bulk delete brands',
            'brands.bulk_restore' => 'Ability to bulk restore brands',
            'brands.bulk_force_delete' => 'Ability to bulk permanently delete brands',
            'brands.export' => 'Ability to export brands to CSV',

            // Master Data - Units
            'units.view' => 'Ability to view units',
            'units.create' => 'Ability to create units',
            'units.update' => 'Ability to update units',
            'units.delete' => 'Ability to delete units',
            'units.restore' => 'Ability to restore soft deleted units',
            'units.force_delete' => 'Ability to permanently delete units',
            'units.bulk_delete' => 'Ability to bulk delete units',
            'units.bulk_restore' => 'Ability to bulk restore units',
            'units.bulk_force_delete' => 'Ability to bulk permanently delete units',
            'units.export' => 'Ability to export units to CSV',

            // Master Data - Products
            'products.view' => 'Ability to view products',
            'products.create' => 'Ability to create products',
            'products.update' => 'Ability to update products',
            'products.delete' => 'Ability to delete products',
            'products.restore' => 'Ability to restore soft deleted products',
            'products.force_delete' => 'Ability to permanently delete products',
            'products.bulk_delete' => 'Ability to bulk delete products',
            'products.bulk_restore' => 'Ability to bulk restore products',
            'products.bulk_force_delete' => 'Ability to bulk permanently delete products',
            'products.export' => 'Ability to export products to CSV',
        ];

        foreach ($permissions as $name => $description) {
            Permission::findOrCreate($name, 'web');
        }

        // 2. Seed Super Admin Role
        $superAdminRole = Role::findOrCreate('Super Admin', 'web');

        // Super Admin gets all permissions assigned
        $superAdminRole->syncPermissions(array_keys($permissions));

        // Store Manager Role
        $managerRole = Role::findOrCreate('Manager', 'web');
        $managerRole->syncPermissions([
            'dashboard.view',
            'users.view',
            'users.create',
            'users.update',

            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.restore',
            'categories.force_delete',
            'categories.bulk_delete',
            'categories.bulk_restore',
            'categories.bulk_force_delete',
            'categories.export',

            'brands.view',
            'brands.create',
            'brands.update',
            'brands.delete',
            'brands.restore',
            'brands.force_delete',
            'brands.bulk_delete',
            'brands.bulk_restore',
            'brands.bulk_force_delete',
            'brands.export',

            'units.view',
            'units.create',
            'units.update',
            'units.delete',
            'units.restore',
            'units.force_delete',
            'units.bulk_delete',
            'units.bulk_restore',
            'units.bulk_force_delete',
            'units.export',

            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.restore',
            'products.force_delete',
            'products.bulk_delete',
            'products.bulk_restore',
            'products.bulk_force_delete',
            'products.export',
        ]);

        $cashierRole = Role::findOrCreate('Cashier', 'web');
        $cashierRole->syncPermissions([
            'dashboard.view',
            'categories.view',
            'brands.view',
            'units.view',
            'products.view',
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

        // 5. Seed some basic Category, Brand, and Unit values for easier setup
        \DB::table('categories')->insert([
            [
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic gadgets and devices',
                'parent_id' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'uuid' => (string) Str::uuid(),
                'name' => 'Apparel',
                'slug' => 'apparel',
                'description' => 'Clothing and wearable items',
                'parent_id' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        \DB::table('brands')->insert([
            [
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'name' => 'Logitech',
                'slug' => 'logitech',
                'description' => 'Premium peripherals manufacturer',
                'logo' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'uuid' => (string) Str::uuid(),
                'name' => 'Nike',
                'slug' => 'nike',
                'description' => 'Athletic apparel and shoes',
                'logo' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        \DB::table('units')->insert([
            [
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'name' => 'Pieces',
                'short_name' => 'pcs',
                'allow_decimal' => 'disallowed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'uuid' => (string) Str::uuid(),
                'name' => 'Kilograms',
                'short_name' => 'kg',
                'allow_decimal' => 'allowed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        \DB::table('products')->insert([
            [
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'name' => 'MX Master 3S Mouse',
                'slug' => 'mx-master-3s-mouse',
                'sku' => 'LOGI-MX3S-01',
                'barcode' => '097855178946',
                'description' => 'Logitech ergonomic office and creator wireless mouse',
                'category_id' => 1,
                'brand_id' => 1,
                'unit_id' => 1,
                'cost_price' => 75.00,
                'selling_price' => 99.99,
                'stock_alert_threshold' => 5.000,
                'current_stock' => 12.000,
                'image' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
