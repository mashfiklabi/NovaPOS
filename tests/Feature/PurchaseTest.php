<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
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
            'allow_decimal' => false,
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

    public function test_create_purchase_with_multiple_items_calculates_server_side_totals(): void
    {
        $product2 = Product::factory()->create([
            'status' => 'active',
            'track_stock' => true,
            'current_stock' => 5,
            'cost_price' => 100.00,
        ]);

        $payload = [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_cost' => 50.00,
                    'discount_amount' => 10.00,
                    'tax_amount' => 5.00,
                ], // Line total: (2*50) - 10 + 5 = 95.00
                [
                    'product_id' => $product2->id,
                    'quantity' => 1,
                    'unit_cost' => 100.00,
                    'discount_amount' => 0,
                    'tax_amount' => 10.00,
                ], // Line total: (1*100) + 10 = 110.00
            ], // Subtotal: 95 + 110 = 205.00
            'discount_amount' => 5.00,
            'tax_amount' => 10.00,
            'shipping_cost' => 15.00, // Grand total: 205 - 5 + 10 + 15 = 225.00
            'paid_amount' => 100.00, // Due: 125.00, PaymentStatus: PARTIAL
        ];

        $response = $this->actingAs($this->adminUser)->post(route('purchases.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchases', [
            'supplier_id' => $this->supplier->id,
            'subtotal' => 205.00,
            'grand_total' => 225.00,
            'paid_amount' => 100.00,
            'due_amount' => 125.00,
            'payment_status' => PaymentStatus::PARTIAL->value,
        ]);
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

        // StockMovement entry recorded with exact balance_after
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 15,
            'balance_after' => 25.00,
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
        ]);

        // Exactly 1 StockMovement record created
        $this->assertEquals(1, StockMovement::where('product_id', $this->product->id)->count());
    }

    public function test_can_record_payment_for_received_purchase_and_update_due_and_payment_status(): void
    {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseStatus::RECEIVED,
            'grand_total' => 500.00,
            'paid_amount' => 0.00,
            'due_amount' => 500.00,
            'payment_status' => PaymentStatus::UNPAID,
        ]);

        // Partial payment of $200
        $response1 = $this->actingAs($this->adminUser)->post(route('purchases.pay', $purchase), ['amount' => 200.00]);
        $response1->assertRedirect();

        $fresh = $purchase->fresh();
        $this->assertEquals(200.00, $fresh->paid_amount);
        $this->assertEquals(300.00, $fresh->due_amount);
        $this->assertEquals(PaymentStatus::PARTIAL, $fresh->payment_status);

        // Remaining payment of $300
        $response2 = $this->actingAs($this->adminUser)->post(route('purchases.pay', $purchase), ['amount' => 300.00]);
        $response2->assertRedirect();

        $fullyPaid = $purchase->fresh();
        $this->assertEquals(500.00, $fullyPaid->paid_amount);
        $this->assertEquals(0.00, $fullyPaid->due_amount);
        $this->assertEquals(PaymentStatus::PAID, $fullyPaid->payment_status);
    }

    public function test_cannot_record_payment_exceeding_due_amount(): void
    {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseStatus::RECEIVED,
            'grand_total' => 500.00,
            'paid_amount' => 400.00,
            'due_amount' => 100.00,
            'payment_status' => PaymentStatus::PARTIAL,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('purchases.pay', $purchase), ['amount' => 200.00]);
        $response->assertSessionHasErrors(['amount']);
        $this->assertEquals(100.00, $purchase->fresh()->due_amount);
    }

    public function test_receiving_purchase_for_non_stock_product_does_not_increment_stock_or_create_movement(): void
    {
        $serviceProduct = Product::factory()->create([
            'status' => 'active',
            'track_stock' => false,
            'current_stock' => 0,
            'cost_price' => 25.00,
        ]);

        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseStatus::DRAFT,
        ]);

        $purchase->items()->create([
            'product_id' => $serviceProduct->id,
            'quantity' => 10,
            'unit_cost' => 25.00,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 250.00,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('purchases.receive', $purchase));

        $response->assertRedirect();
        $this->assertEquals(0, $serviceProduct->fresh()->current_stock);
        $this->assertEquals(0, StockMovement::where('product_id', $serviceProduct->id)->count());
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

    public function test_decimal_quantity_validation_respects_allow_decimal_flag(): void
    {
        // $this->product has allow_decimal = false
        $payload = [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2.5, // Invalid decimal quantity
                    'unit_cost' => 50.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->post(route('purchases.store'), $payload);

        $response->assertSessionHasErrors(['items.0.quantity']);
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
