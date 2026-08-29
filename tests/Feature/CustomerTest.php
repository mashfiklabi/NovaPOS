<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->adminUser = User::factory()->create(['status' => 'active']);
        $this->adminUser->assignRole($role);
    }

    public function test_can_display_customers_index(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)->get(route('customers.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_customer(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('customers.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create();

        $payload = [
            'name' => 'Updated Customer',
            'email' => 'updated@example.com',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)->put(route('customers.update', $customer), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Customer',
        ]);
    }

    public function test_can_soft_delete_and_restore_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete(route('customers.destroy', $customer));
        $response->assertRedirect();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $restoreResponse = $this->actingAs($this->adminUser)->post(route('customers.restore', $customer->id));
        $restoreResponse->assertRedirect();
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_unauthorized_user_cannot_access_customers(): void
    {
        $unauthorizedUser = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($unauthorizedUser)->get(route('customers.index'));

        $response->assertStatus(403);
    }
}
