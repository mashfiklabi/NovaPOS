<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CreditLedgerType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SalePaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\CustomerCreditLedger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleRefund;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaleRefundAndCreditTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $cashier;

    protected Customer $customer;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::findOrCreate('Super Admin', 'web');
        $roleCashier = Role::findOrCreate('Cashier', 'web');

        $permissions = [
            'sales.view', 'sales.create', 'sales.cancel', 'sales.refund', 'sales.payment',
            'customers.view', 'customers.credit',
        ];

        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $roleAdmin->syncPermissions($permissions);
        $roleCashier->syncPermissions(['sales.view', 'sales.create', 'sales.payment']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($roleAdmin);

        $this->cashier = User::factory()->create();
        $this->cashier->assignRole($roleCashier);

        Setting::create(['key' => 'refund_enabled', 'value' => 'true', 'group' => 'pos', 'type' => 'boolean']);
        Setting::create(['key' => 'refund_percentage', 'value' => '100', 'group' => 'pos', 'type' => 'number']);
        Setting::create(['key' => 'store_credit_enabled', 'value' => 'true', 'group' => 'pos', 'type' => 'boolean']);

        $this->customer = Customer::factory()->create(['status' => 'active']);
        $this->product = Product::factory()->create([
            'selling_price' => 100.00,
            'current_stock' => 50,
            'track_stock' => true,
            'status' => 'active',
        ]);
    }

    public function test_can_process_full_refund_for_cancelled_sale(): void
    {
        $sale = $this->createCancelledSaleWithPayment(100.00, 100.00);

        $response = $this->actingAs($this->superAdmin)->post(route('sales.refund', $sale->id), [
            'amount' => 100.00,
            'refund_method' => 'cash',
            'reason' => 'Customer requested cash refund',
            'reference_number' => 'REF-123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sale_refunds', [
            'sale_id' => $sale->id,
            'amount' => 100.00,
            'refund_method' => 'cash',
            'status' => RefundStatus::COMPLETED->value,
        ]);
    }

    public function test_refund_cannot_exceed_eligible_paid_amount(): void
    {
        $sale = $this->createCancelledSaleWithPayment(100.00, 60.00);

        $response = $this->actingAs($this->superAdmin)->post(route('sales.refund', $sale->id), [
            'amount' => 80.00,
            'refund_method' => 'cash',
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseMissing('sale_refunds', [
            'sale_id' => $sale->id,
        ]);
    }

    public function test_duplicate_refund_is_prevented(): void
    {
        $sale = $this->createCancelledSaleWithPayment(100.00, 100.00);

        // First refund of 60.00
        $this->actingAs($this->superAdmin)->post(route('sales.refund', $sale->id), [
            'amount' => 60.00,
            'refund_method' => 'cash',
        ]);

        // Attempt second refund of 50.00 (Total 110 > Paid 100)
        $response = $this->actingAs($this->superAdmin)->post(route('sales.refund', $sale->id), [
            'amount' => 50.00,
            'refund_method' => 'cash',
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertEquals(1, SaleRefund::where('sale_id', $sale->id)->count());
    }

    public function test_can_convert_cancelled_sale_payment_to_store_credit(): void
    {
        $sale = $this->createCancelledSaleWithPayment(100.00, 100.00);

        $response = $this->actingAs($this->superAdmin)->post(route('sales.credit', $sale->id), [
            'amount' => 100.00,
            'reason' => 'Convert to store credit',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_credit_ledgers', [
            'customer_id' => $this->customer->id,
            'sale_id' => $sale->id,
            'type' => CreditLedgerType::CREDIT->value,
            'amount' => 100.00,
            'balance_after' => 100.00,
        ]);

        $this->assertEquals(100.00, $this->customer->fresh()->store_credit_balance);
    }

    public function test_store_credit_can_be_used_in_new_pos_sale(): void
    {
        // Give customer 100 store credit
        CustomerCreditLedger::create([
            'customer_id' => $this->customer->id,
            'type' => CreditLedgerType::CREDIT,
            'amount' => 100.00,
            'balance_after' => 100.00,
            'created_by' => $this->superAdmin->id,
        ]);

        // Process a new POS sale using store credit
        // Process a new POS sale using store credit
        $response = $this->actingAs($this->superAdmin)->post(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
            'paid_amount' => 100.00,
            'payment_method' => SalePaymentMethod::STORE_CREDIT->value,
            'status' => SaleStatus::COMPLETED->value,
        ]);

        $response->assertRedirect();
        $this->assertEquals(0.00, $this->customer->fresh()->store_credit_balance);

        $this->assertDatabaseHas('customer_credit_ledgers', [
            'customer_id' => $this->customer->id,
            'type' => CreditLedgerType::DEBIT->value,
            'amount' => 100.00,
            'balance_after' => 0.00,
        ]);
    }

    public function test_store_credit_payment_fails_if_insufficient_credit(): void
    {
        CustomerCreditLedger::create([
            'customer_id' => $this->customer->id,
            'type' => CreditLedgerType::CREDIT,
            'amount' => 50.00,
            'balance_after' => 50.00,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
            'paid_amount' => 100.00,
            'payment_method' => SalePaymentMethod::STORE_CREDIT->value,
            'status' => SaleStatus::COMPLETED->value,
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertEquals(50.00, $this->customer->fresh()->store_credit_balance);
    }

    public function test_unauthorized_user_cannot_refund_or_issue_credit(): void
    {
        $sale = $this->createCancelledSaleWithPayment(100.00, 100.00);

        // Cashier role lacks sales.refund and customers.credit
        $response = $this->actingAs($this->cashier)->post(route('sales.refund', $sale->id), [
            'amount' => 100.00,
            'refund_method' => 'cash',
        ]);
        $response->assertStatus(403);

        $responseCredit = $this->actingAs($this->cashier)->post(route('sales.credit', $sale->id), [
            'amount' => 100.00,
        ]);
        $responseCredit->assertStatus(403);
    }

    protected function createCancelledSaleWithPayment(float $grandTotal, float $paidAmount): Sale
    {
        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-001',
            'customer_id' => $this->customer->id,
            'user_id' => $this->superAdmin->id,
            'sale_date' => now(),
            'subtotal' => $grandTotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'due_amount' => round($grandTotal - $paidAmount, 2),
            'payment_status' => $paidAmount >= $grandTotal ? PaymentStatus::PAID : PaymentStatus::PARTIAL,
            'status' => SaleStatus::CANCELLED,
            'created_by' => $this->superAdmin->id,
        ]);

        if ($paidAmount > 0) {
            $sale->payments()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $this->superAdmin->id,
                'payment_method' => 'cash',
                'amount' => $paidAmount,
                'paid_at' => now(),
            ]);
        }

        return $sale;
    }
}
