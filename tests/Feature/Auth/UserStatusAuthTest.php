<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@novapos.com',
            'password' => 'Password123!',
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@novapos.com',
            'password' => 'Password123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    public function test_inactive_user_cannot_login_and_receives_inactive_message(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@novapos.com',
            'password' => 'Password123!',
            'status' => UserStatus::INACTIVE,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@novapos.com',
            'password' => 'Password123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Your account is inactive. Please contact the administrator.',
        ]);
    }

    public function test_suspended_user_cannot_login_and_receives_suspended_message(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@novapos.com',
            'password' => 'Password123!',
            'status' => UserStatus::SUSPENDED,
        ]);

        $response = $this->post('/login', [
            'email' => 'suspended@novapos.com',
            'password' => 'Password123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Your account has been suspended. Please contact the administrator.',
        ]);
    }

    public function test_invalid_credentials_fail_with_standard_message(): void
    {
        User::factory()->create([
            'email' => 'user@novapos.com',
            'password' => 'Password123!',
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->post('/login', [
            'email' => 'user@novapos.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email']);
    }

    public function test_middleware_logs_out_active_user_who_becomes_inactive(): void
    {
        $user = User::factory()->create([
            'email' => 'user@novapos.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($user);

        // Deactivate user in database
        $user->update(['status' => UserStatus::INACTIVE]);

        // Attempt accessing protected dashboard route
        $response = $this->get('/dashboard');

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'Your account is inactive. Please contact the administrator.',
        ]);
    }

    public function test_middleware_logs_out_active_user_who_becomes_suspended(): void
    {
        $user = User::factory()->create([
            'email' => 'user@novapos.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($user);

        // Suspend user in database
        $user->update(['status' => UserStatus::SUSPENDED]);

        // Attempt accessing protected dashboard route
        $response = $this->get('/dashboard');

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'Your account has been suspended. Please contact the administrator.',
        ]);
    }
}
