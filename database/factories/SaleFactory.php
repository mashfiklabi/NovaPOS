<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'sale_date' => now()->format('Y-m-d'),
            'status' => SaleStatus::COMPLETED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => 200.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'shipping_cost' => 0.00,
            'grand_total' => 200.00,
            'paid_amount' => 200.00,
            'due_amount' => 0.00,
        ];
    }
}
