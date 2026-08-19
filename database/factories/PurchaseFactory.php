<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'purchase_date' => now()->format('Y-m-d'),
            'expected_delivery_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => PurchaseStatus::DRAFT,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal' => 500.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'shipping_cost' => 0.00,
            'grand_total' => 500.00,
            'paid_amount' => 0.00,
            'due_amount' => 500.00,
            'created_by' => User::factory(),
        ];
    }
}
