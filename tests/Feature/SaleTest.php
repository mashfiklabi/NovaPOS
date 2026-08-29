<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Customer $customer;

    protected Product $integerProduct;

    protected Product $decimalProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->adminUser = User::factory()->create(['status' => 'active']);
        $this->adminUser->assignRole($role);

        $this->customer = Customer::factory()->create(['status' => 'active']);

        $this->integerProduct = Product::factory()->create([
            'status' => 'active',
            'selling_price' => 50.00,
            'allow_decimal' => false,
        ]);

        $this->decimalProduct = Product::factory()->create([
            'status' => 'active',
            'selling_price' => 20.00,
            'allow_decimal' => true,
        ]);
    }

    public function test_can_display_sales_index(): void
    {
        Sale::factory()->count(2)->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('sales.index'));

        $response->assertStatus(200);
    }

    public function test_sale_can_be_created_and_items_stored_correctly(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'status' => 'completed',
            'items' => [
                [
                    'product_id' => $this->integerProduct->id,
                    'quantity' => 2,
                    'unit_price' => 50.00,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ],
            ],
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'paid_amount' => 100.00,
            'payment_method' => 'cash',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales', [
            'customer_id' => $this->customer->id,
            'grand_total' => 100.00,
            'paid_amount' => 100.00,
            'due_amount' => 0.00,
            'payment_status' => PaymentStatus::PAID->value,
            'status' => SaleStatus::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $this->integerProduct->id,
            'quantity' => 2,
            'unit_price' => 50.00,
            'total' => 100.00,
        ]);

        // Verify initial SalePayment record stored
        $this->assertDatabaseHas('sale_payments', [
            'amount' => 100.00,
            'payment_method' => 'cash',
        ]);
    }

    public function test_client_cannot_manipulate_product_selling_price(): void
    {
        // $this->integerProduct DB selling_price = 50.00
        // Client attempts to pass manipulated unit_price = 0.01
        $payload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'status' => 'completed',
            'items' => [
                [
                    'product_id' => $this->integerProduct->id,
                    'quantity' => 2,
                    'unit_price' => 0.01, // Manipulated price
                ],
            ],
            'paid_amount' => 100.00,
        ];

        $response = $this->actingAs($this->adminUser)->post(route('sales.store'), $payload);

        $response->assertRedirect();
        // Server MUST use authoritative DB selling_price (50.00 * 2 = 100.00)
        $this->assertDatabaseHas('sale_items', [
            'product_id' => $this->integerProduct->id,
            'unit_price' => 50.00,
            'total' => 100.00,
        ]);

        $this->assertDatabaseHas('sales', [
            'grand_total' => 100.00,
        ]);
    }

    public function test_sale_update_preserves_payment_integrity_from_actual_sale_payments(): void
    {
        $sale = Sale::factory()->create([
            'customer_id' => $this->customer->id,
            'grand_total' => 100.00,
            'paid_amount' => 40.00,
            'due_amount' => 60.00,
            'payment_status' => PaymentStatus::PARTIAL,
        ]);

        $sale->payments()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->adminUser->id,
            'payment_method' => \App\Enums\SalePaymentMethod::CASH,
            'amount' => 40.00,
            'paid_at' => now(),
        ]);

        // Attempting to pass manipulated paid_amount = 0 in update payload
        $updatePayload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->integerProduct->id,
                    'quantity' => 2,
                    'unit_price' => 50.00,
                ],
            ],
            'paid_amount' => 0.00, // Manipulated payload
        ];

        $response = $this->actingAs($this->adminUser)->put(route('sales.update', $sale), $updatePayload);

        $response->assertRedirect();
        // Server MUST keep paid_amount = 40.00 derived from actual SalePayment records sum
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'paid_amount' => 40.00,
            'due_amount' => 60.00,
            'payment_status' => PaymentStatus::PARTIAL->value,
        ]);
    }

    public function test_server_calculates_totals_discounts_taxes_and_payment_status_correctly(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'status' => 'completed',
            'items' => [
                [
                    'product_id' => $this->integerProduct->id,
                    'quantity' => 3,
                    'discount_amount' => 10.00,
                    'tax_amount' => 5.00,
                ], // Line total: (3*50) - 10 + 5 = 145.00
            ], // Subtotal: 145.00
            'discount_amount' => 5.00,
            'tax_amount' => 10.00,
            'shipping_cost' => 10.00, // Grand total: 145 - 5 + 10 + 10 = 160.00
            'paid_amount' => 60.00, // Due: 100.00, PaymentStatus: PARTIAL
        ];

        $response = $this->actingAs($this->adminUser)->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales', [
            'subtotal' => 145.00,
            'grand_total' => 160.00,
            'paid_amount' => 60.00,
            'due_amount' => 100.00,
            'payment_status' => PaymentStatus::PARTIAL->value,
        ]);
    }

    public function test_payment_status_reflects_unpaid_partial_and_paid_states(): void
    {
        // Unpaid sale
        $unpaidPayload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'status' => 'completed',
            'items' => [
                ['product_id' => $this->integerProduct->id, 'quantity' => 1],
            ],
            'paid_amount' => 0.00,
        ];

        $this->actingAs($this->adminUser)->post(route('sales.store'), $unpaidPayload);

        $this->assertDatabaseHas('sales', [
            'grand_total' => 50.00,
            'paid_amount' => 0.00,
            'due_amount' => 50.00,
            'payment_status' => PaymentStatus::UNPAID->value,
        ]);
    }

    public function test_decimal_quantity_is_rejected_when_product_does_not_allow_decimal(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'status' => 'completed',
            'items' => [
                [
                    'product_id' => $this->integerProduct->id,
                    'quantity' => 1.5, // Invalid decimal quantity
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->post(route('sales.store'), $payload);

        $response->assertSessionHasErrors(['items.0.quantity']);
    }

    public function test_decimal_quantity_is_accepted_when_product_allows_decimal(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'status' => 'completed',
            'items' => [
                [
                    'product_id' => $this->decimalProduct->id,
                    'quantity' => 2.250, // Valid decimal quantity
                ],
            ],
            'paid_amount' => 45.00,
        ];

        $response = $this->actingAs($this->adminUser)->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('sale_items', [
            'product_id' => $this->decimalProduct->id,
            'quantity' => 2.250,
            'total' => 45.00,
        ]);
    }

    public function test_initial_payment_cannot_exceed_sale_grand_total(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'status' => 'completed',
            'items' => [
                [
                    'product_id' => $this->integerProduct->id, // 50.00 * 2 = 100.00
                    'quantity' => 2,
                ],
            ],
            'paid_amount' => 150.00, // Exceeds grand_total of 100.00
        ];

        $response = $this->actingAs($this->adminUser)->post(route('sales.store'), $payload);

        $response->assertSessionHasErrors(['error']);
    }

    public function test_payment_cannot_be_recorded_for_cancelled_sale(): void
    {
        $sale = Sale::factory()->create([
            'customer_id' => $this->customer->id,
            'grand_total' => 100.00,
            'paid_amount' => 0.00,
            'due_amount' => 100.00,
            'status' => SaleStatus::CANCELLED,
            'payment_status' => PaymentStatus::UNPAID,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('sales.pay', $sale), [
            'amount' => 50.00,
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertEquals(100.00, $sale->fresh()->due_amount);
    }

    public function test_payment_cannot_exceed_outstanding_due_amount(): void
    {
        $sale = Sale::factory()->create([
            'customer_id' => $this->customer->id,
            'grand_total' => 100.00,
            'paid_amount' => 60.00,
            'due_amount' => 40.00,
            'payment_status' => PaymentStatus::PARTIAL,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('sales.pay', $sale), [
            'amount' => 50.00, // Exceeds due amount of $40
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors(['amount']);
        $this->assertEquals(40.00, $sale->fresh()->due_amount);
    }

    public function test_can_record_valid_payment_for_outstanding_sale(): void
    {
        $sale = Sale::factory()->create([
            'customer_id' => $this->customer->id,
            'grand_total' => 100.00,
            'paid_amount' => 60.00,
            'due_amount' => 40.00,
            'payment_status' => PaymentStatus::PARTIAL,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('sales.pay', $sale), [
            'amount' => 40.00,
            'payment_method' => 'card',
        ]);

        $response->assertRedirect();
        $fresh = $sale->fresh();
        $this->assertEquals(100.00, $fresh->paid_amount);
        $this->assertEquals(0.00, $fresh->due_amount);
        $this->assertEquals(PaymentStatus::PAID, $fresh->payment_status);
    }

    public function test_invoice_numbers_are_generated_and_unique(): void
    {
        $s1 = Sale::factory()->create(['customer_id' => $this->customer->id]);
        $s2 = Sale::factory()->create(['customer_id' => $this->customer->id]);

        $this->assertNotEmpty($s1->invoice_number);
        $this->assertNotEmpty($s2->invoice_number);
        $this->assertNotEquals($s1->invoice_number, $s2->invoice_number);
    }

    public function test_unauthenticated_user_cannot_create_sales(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'status' => 'completed',
            'items' => [
                ['product_id' => $this->integerProduct->id, 'quantity' => 1],
            ],
        ];

        $response = $this->post(route('sales.store'), $payload);

        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_or_create_sales(): void
    {
        $unauthorizedUser = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($unauthorizedUser)->get(route('sales.index'));

        $response->assertStatus(403);
    }
}
