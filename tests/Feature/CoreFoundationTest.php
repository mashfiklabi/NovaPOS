<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoreFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic permissions
        Permission::create(['name' => 'dashboard.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'settings.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'settings.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.delete', 'guard_name' => 'web']);

        // Seed settings
        Setting::create([
            'key' => 'shop_name',
            'value' => 'NovaPOS Retail',
            'group' => 'general',
            'type' => 'text',
        ]);
        Setting::create([
            'key' => 'phone',
            'value' => '+1 (555) 019-2834',
            'group' => 'general',
            'type' => 'text',
        ]);
        Setting::create([
            'key' => 'email',
            'value' => 'info@novapos.com',
            'group' => 'general',
            'type' => 'text',
        ]);
        Setting::create([
            'key' => 'address',
            'value' => '123 Tech Avenue, Silicon Valley, CA',
            'group' => 'general',
            'type' => 'textarea',
        ]);
        Setting::create([
            'key' => 'currency',
            'value' => 'USD',
            'group' => 'localization',
            'type' => 'text',
        ]);
        Setting::create([
            'key' => 'timezone',
            'value' => 'UTC',
            'group' => 'localization',
            'type' => 'text',
        ]);
        Setting::create([
            'key' => 'invoice_prefix',
            'value' => 'INV-',
            'group' => 'pos',
            'type' => 'text',
        ]);
        Setting::create([
            'key' => 'tax_rate',
            'value' => '8.25',
            'group' => 'pos',
            'type' => 'number',
        ]);
        Setting::create([
            'key' => 'logo',
            'value' => null,
            'group' => 'appearance',
            'type' => 'file',
        ]);
        Setting::create([
            'key' => 'favicon',
            'value' => null,
            'group' => 'appearance',
            'type' => 'file',
        ]);
    }

    // ==========================================
    // AUTHENTICATION TESTS
    // ==========================================

    public function test_authenticated_user_can_login_and_logout_safely(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'status' => UserStatus::ACTIVE,
        ]);

        // Attempt login
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');

        // Check Login Activity Logged
        $loginActivity = Activity::where('event', 'login')->first();
        $this->assertNotNull($loginActivity);
        $this->assertSame($user->id, $loginActivity->causer_id);

        // Attempt logout
        $response = $this->actingAs($user)->post('/logout');
        $this->assertGuest();
        $response->assertRedirect('/');

        // Check Logout Activity Logged
        $logoutActivity = Activity::where('event', 'logout')->first();
        $this->assertNotNull($logoutActivity);
    }

    public function test_user_can_update_profile_and_contact(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $avatarFile = UploadedFile::fake()->image('my_avatar.png');

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Associate Name',
            'email' => 'updated_email@novapos.com',
            'phone' => '+1 (555) 999-8888',
            'avatar' => $avatarFile,
        ]);

        $response->assertRedirect('/profile');
        $user->refresh();

        $this->assertSame('Updated Associate Name', $user->name);
        $this->assertSame('updated_email@novapos.com', $user->email);
        $this->assertSame('+1 (555) 999-8888', $user->phone);
        $this->assertNotNull($user->avatar);

        Storage::disk('public')->assertExists($user->avatar);
    }

    // ==========================================
    // RBAC TESTS
    // ==========================================

    public function test_super_admin_role_can_bypass_all_permissions(): void
    {
        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $admin->assignRole($superAdminRole);

        // Assert has wildcard permission
        $this->assertTrue($admin->can('dashboard.view'));
        $this->assertTrue($admin->can('settings.update'));
    }

    public function test_unauthorized_users_receive_403_responses(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        // Attempt settings page access without permission
        $response = $this->actingAs($user)->get('/settings');
        $response->assertStatus(403);
    }

    public function test_manager_role_can_be_assigned_and_authorized(): void
    {
        $managerRole = Role::create(['name' => 'Store Manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo('dashboard.view');

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole($managerRole);

        $this->assertTrue($user->hasRole('Store Manager'));
        $this->assertTrue($user->hasPermissionTo('dashboard.view'));
        $this->assertFalse($user->hasPermissionTo('settings.view'));
    }

    // ==========================================
    // NAVIGATION TESTS
    // ==========================================

    public function test_dynamic_navigation_shares_authorized_menus_and_hides_unauthorized(): void
    {
        $clerkRole = Role::create(['name' => 'Inventory Clerk', 'guard_name' => 'web']);
        $clerkRole->givePermissionTo('dashboard.view');

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole($clerkRole);

        // Visit any route to trigger share() in HandleInertiaRequests middleware
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $navigation = $response->original->getData()['page']['props']['navigation'];

        // Confirm dashboard visible, settings hidden
        $menuTitles = collect($navigation)->pluck('title')->toArray();
        $this->assertContains('Dashboard', $menuTitles);
        $this->assertNotContains('Settings', $menuTitles);
    }

    // ==========================================
    // SETTINGS TESTS
    // ==========================================

    public function test_authorized_user_can_view_and_update_settings(): void
    {
        $settingsRole = Role::create(['name' => 'Config Admin', 'guard_name' => 'web']);
        $settingsRole->givePermissionTo(['settings.view', 'settings.update']);

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole($settingsRole);

        // View settings
        $response = $this->actingAs($user)->get('/settings');
        $response->assertOk();

        // Update settings
        $response = $this->actingAs($user)->post('/settings', [
            'shop_name' => 'Updated Retail Shop Name',
            'phone' => '+1 (555) 777-1234',
            'email' => 'contact@novapos-updated.com',
            'address' => 'Updated Silicon Valley Address',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'invoice_prefix' => 'POS-',
            'tax_rate' => 19.6,
        ]);

        $response->assertRedirect();

        // Assert updated in database
        $this->assertSame('Updated Retail Shop Name', Setting::where('key', 'shop_name')->first()->value);
        $this->assertSame('POS-', Setting::where('key', 'invoice_prefix')->first()->value);
    }

    public function test_settings_validation_prevents_corrupt_data(): void
    {
        $settingsRole = Role::create(['name' => 'Config Admin', 'guard_name' => 'web']);
        $settingsRole->givePermissionTo(['settings.view', 'settings.update']);

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole($settingsRole);

        // Send invalid phone email, and negative tax rate
        $response = $this->actingAs($user)->post('/settings', [
            'shop_name' => '', // Required
            'phone' => '123',
            'email' => 'not-an-email',
            'address' => 'Silicon Valley',
            'currency' => '', // Required
            'timezone' => 'UTC',
            'invoice_prefix' => 'INV-',
            'tax_rate' => -5, // Needs to be positive
        ]);

        $response->assertSessionHasErrors(['shop_name', 'email', 'currency', 'tax_rate']);
    }

    // ==========================================
    // DASHBOARD ACCESS TESTS
    // ==========================================

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    // ==========================================
    // ACTIVITY LOGS CRUD AUDITS
    // ==========================================

    public function test_crud_mutations_automatically_log_to_spatie_activity_table(): void
    {
        $settingsRole = Role::create(['name' => 'Config Admin', 'guard_name' => 'web']);
        $settingsRole->givePermissionTo(['settings.view', 'settings.update']);

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole($settingsRole);

        // Trigger settings model update
        $this->actingAs($user)->post('/settings', [
            'shop_name' => 'Brand New Retail Shop',
            'phone' => '+1 (555) 777-1234',
            'email' => 'contact@novapos.com',
            'address' => 'Updated Silicon Valley Address',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'invoice_prefix' => 'POS-',
            'tax_rate' => 19.6,
        ]);

        // Spatie activity logged automatically (grab latest)
        $activity = Activity::where('subject_type', Setting::class)->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertSame('updated', $activity->event);
    }
}
