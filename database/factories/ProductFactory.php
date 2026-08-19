<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'barcode' => fake()->unique()->ean13(),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'unit_id' => Unit::factory(),
            'cost_price' => 50.00,
            'selling_price' => 75.00,
            'stock_alert_threshold' => 5,
            'current_stock' => 20,
            'status' => 'active',
            'track_stock' => true,
            'allow_decimal' => false,
            'tax_type' => 'exclusive',
            'tax_rate' => 0.00,
        ];
    }
}
