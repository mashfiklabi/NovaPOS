<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:products,name',
            'sku' => 'required|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'description' => 'nullable|string',
            'category_id' => 'required|integer|exists:categories,id', // Category strictly required
            'brand_id' => 'nullable|integer|exists:brands,id',
            'unit_id' => 'required|integer|exists:units,id',
            'cost_price' => 'required|numeric|min:0', // represents Purchase Price
            'selling_price' => 'required|numeric|min:0',
            'stock_alert_threshold' => 'required|numeric|min:0', // represents Minimum Stock
            'current_stock' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'status' => 'required|string|in:active,inactive,out_of_stock,discontinued',
            'track_stock' => 'nullable|boolean',
            'allow_decimal' => 'nullable|boolean',
            'tax_type' => 'required|string|in:exclusive,inclusive,none',
            'tax_rate' => 'required|numeric|min:0',
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'brand_id' => 'brand',
            'unit_id' => 'unit',
            'cost_price' => 'purchase price',
            'stock_alert_threshold' => 'minimum stock threshold',
        ];
    }
}
