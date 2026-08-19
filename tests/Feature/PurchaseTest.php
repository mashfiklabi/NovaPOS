<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Supplier $supplier;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->adminUser = User::factory()->create(['status' => 'active']);
        $this->adminUser->assignRole($role);

        $this->supplier = Supplier::factory()->create(['status' => 'active']);
        $this->product = Product::factory()->create([
            'status' => 'active',
            'track_stock' => true,
            'current_stock' => 10,
            'cost_price' => 50.00,
        ]);
    }

    public function test_can_display_purchases_index(): void
    {
        Purchase::factory()->count(2)->create([
            'supplier_id' => $this->supplier->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('purchases.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_purchase_order_in_draft_status(): void
    {
        $payload = [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_cost' => 50.00,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ],
            ],
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_cost' => 10.00,
            'paid_amount' => 0,
        ];

        $response = $this->actingAs($this->adminUser)->post(route('purchases.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchases', [
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseStatus::DRAFT->value,
            'grand_total' => 260.00, // (5*50) + 10 shipping
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_cost' => 50.00,
        ]);

        // Stock should remain unchanged in draft status
        $this->assertEquals(10, $this->product->fresh()->current_stock);
    }

    public function test_receiving_purchase_increments_stock_and_creates_stock_movement(): void
    {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseStatus::DRAFT,
        ]);

        $purchase->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 15,
            'unit_cost' => 50.00,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 750.00,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('purchases.receive', $purchase));

        $response->assertRedirect();
        $this->assertEquals(PurchaseStatus::RECEIVED, $purchase->fresh()->status);

        // Product stock should increment from 10 to 25
        $this->assertEquals(25, $this->product->fresh()->current_stock);

        // StockMovement entry recorded
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 15,
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
        ]);
    }

    public function test_cannot_receive_already_received_purchase(): void
    {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseStatus::RECEIVED,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('purchases.receive', $purchase));

        $response->assertRedirect();
        $response->assertSessionHasErrors(['error']);
    }

    public function test_can_cancel_draft_purchase(): void
    {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseStatus::DRAFT,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('purchases.cancel', $purchase));

        $response->assertRedirect();
        $this->assertEquals(PurchaseStatus::CANCELLED, $purchase->fresh()->status);
    }

    public function test_can_soft_delete_and_restore_purchase(): void
    {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('purchases.destroy', $purchase));

        $response->assertRedirect();
        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);

        $restoreResponse = $this->actingAs($this->adminUser)->post(route('purchases.restore', $purchase->id));

        $restoreResponse->assertRedirect();
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'deleted_at' => null]);
    }
}
