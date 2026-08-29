<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalePaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalePayment>
 */
class SalePaymentFactory extends Factory
{
    protected $model = SalePayment::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'user_id' => User::factory(),
            'payment_method' => SalePaymentMethod::CASH,
            'amount' => 200.00,
            'paid_at' => now(),
        ];
    }
}
