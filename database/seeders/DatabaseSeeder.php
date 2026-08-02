<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Permissions
        $permissions = [
            'view_dashboard' => 'Ability to view the main dashboard metrics and charts',
            'manage_users' => 'Ability to create, update, delete, and list users',
            'manage_roles' => 'Ability to manage roles and permission assignments',
            'manage_settings' => 'Ability to edit global system and shop configuration settings',
            'view_audit_logs' => 'Ability to inspect system audit logs and history',
        ];

        $permissionModels = [];
        foreach ($permissions as $name => $description) {
            $permissionModels[$name] = Permission::create([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'description' => $description,
            ]);
        }

        // 2. Seed Roles
        $roles = [
            'Admin' => 'Full administrative access to the entire system',
            'Manager' => 'Store manager with access to dashboard and user management',
            'Cashier' => 'Point of sale cashier with basic access to POS and dashboard',
        ];

        $roleModels = [];
        foreach ($roles as $name => $description) {
            $roleModels[$name] = Role::create([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'description' => $description,
            ]);
        }

        // 3. Assign Permissions to Roles
        // Admin gets all
        $roleModels['Admin']->permissions()->sync(collect($permissionModels)->pluck('id')->toArray());

        // Manager gets view_dashboard and manage_users
        $roleModels['Manager']->permissions()->sync([
            $permissionModels['view_dashboard']->id,
            $permissionModels['manage_users']->id,
        ]);

        // Cashier gets view_dashboard
        $roleModels['Cashier']->permissions()->sync([
            $permissionModels['view_dashboard']->id,
        ]);

        // 4. Seed Default Admin User
        $admin = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@novapos.com',
            'password' => 'Password123!', // Automatic hashed cast in User model
            'status' => User::STATUS_ACTIVE,
        ]);
        $admin->roles()->sync([$roleModels['Admin']->id]);

        // Seed some standard users with other roles for preview / testing
        $manager = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Jane Manager',
            'email' => 'manager@novapos.com',
            'password' => 'Password123!',
            'status' => User::STATUS_ACTIVE,
        ]);
        $manager->roles()->sync([$roleModels['Manager']->id]);

        $cashier = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'John Cashier',
            'email' => 'cashier@novapos.com',
            'password' => 'Password123!',
            'status' => User::STATUS_ACTIVE,
        ]);
        $cashier->roles()->sync([$roleModels['Cashier']->id]);

        // 5. Seed Key-Value Settings
        $settings = [
            'shop_name' => 'NovaPOS Retail',
            'phone' => '+1 (555) 019-2834',
            'address' => '123 Tech Avenue, Silicon Valley, CA',
            'currency' => 'USD',
            'invoice_prefix' => 'INV-',
            'tax_rate' => '8.25',
            'timezone' => 'UTC',
            'logo' => null,
            'favicon' => null,
        ];

        foreach ($settings as $key => $value) {
            Setting::create([
                'key' => $key,
                'value' => $value,
            ]);
        }
    }
}
