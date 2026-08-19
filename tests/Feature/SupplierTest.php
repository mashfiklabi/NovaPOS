<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    public function test_can_display_suppliers_index(): void
    {
        Supplier::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)->get(route('suppliers.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_supplier(): void
    {
        $supplierData = [
            'name' => 'Acme Wholesale Corp',
            'company_name' => 'Acme Inc',
            'email' => 'contact@acmewholesale.com',
            'phone' => '+15551234567',
            'city' => 'New York',
            'country' => 'USA',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('suppliers.store'), $supplierData);

        $response->assertRedirect();
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Acme Wholesale Corp',
            'email' => 'contact@acmewholesale.com',
        ]);
    }

    public function test_can_update_supplier(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Old Supplier Name']);

        $response = $this->actingAs($this->adminUser)->put(route('suppliers.update', $supplier), [
            'name' => 'Updated Supplier Name',
            'company_name' => $supplier->company_name,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier Name',
        ]);
    }

    public function test_can_soft_delete_and_restore_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete(route('suppliers.destroy', $supplier));

        $response->assertRedirect();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);

        $restoreResponse = $this->actingAs($this->adminUser)->post(route('suppliers.restore', $supplier->id));

        $restoreResponse->assertRedirect();
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'deleted_at' => null,
        ]);
    }

    public function test_can_bulk_delete_and_bulk_restore_suppliers(): void
    {
        $s1 = Supplier::factory()->create();
        $s2 = Supplier::factory()->create();

        $response = $this->actingAs($this->adminUser)->post(route('suppliers.bulk-delete'), [
            'ids' => [$s1->id, $s2->id],
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('suppliers', ['id' => $s1->id]);
        $this->assertSoftDeleted('suppliers', ['id' => $s2->id]);

        $restoreResponse = $this->actingAs($this->adminUser)->post(route('suppliers.bulk-restore'), [
            'ids' => [$s1->id, $s2->id],
        ]);

        $restoreResponse->assertRedirect();
        $this->assertDatabaseHas('suppliers', ['id' => $s1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('suppliers', ['id' => $s2->id, 'deleted_at' => null]);
    }

    public function test_unauthorized_user_cannot_access_suppliers(): void
    {
        $unauthorizedUser = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($unauthorizedUser)->get(route('suppliers.index'));

        $response->assertStatus(403);
    }
}
