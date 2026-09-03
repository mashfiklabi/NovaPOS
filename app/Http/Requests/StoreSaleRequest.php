<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Sale::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sale_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,card,bank_transfer,store_credit,other'],
            'status' => ['required', 'string', 'in:draft,completed,cancelled'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            if (! is_array($items) || empty($items)) {
                return;
            }

            $productIds = array_filter(array_column($items, 'product_id'));
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($items as $index => $item) {
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    continue;
                }

                $product = $products->get($item['product_id']);
                if ($product && ! $product->allow_decimal) {
                    $qty = (float) $item['quantity'];
                    if (floor($qty) != $qty || $qty < 1) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Product '{$product->name}' does not allow fractional/decimal quantities."
                        );
                    }
                }
            }
        });
    }
}
