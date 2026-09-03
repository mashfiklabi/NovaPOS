<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'name' => "required|string|max:255|unique:products,name,{$productId}",
            'sku' => "required|string|max:100|unique:products,sku,{$productId}",
            'barcode' => "nullable|string|max:100|unique:products,barcode,{$productId}",
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'unit_id' => 'required|integer|exists:units,id',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_alert_threshold' => 'required|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'status' => 'required|string|in:active,inactive,out_of_stock,discontinued',
            'track_stock' => 'nullable|boolean',
            'allow_decimal' => 'nullable|boolean',
            'tax_type' => 'required|string|in:exclusive,inclusive,none',
            'tax_rate' => 'required|numeric|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $unitId = $this->input('unit_id');
            if (! $unitId) {
                return;
            }

            $unit = Unit::find($unitId);
            if ($unit && $unit->allow_decimal === 'disallowed') {
                $stock = $this->input('current_stock');
                if ($stock !== null && floor((float) $stock) != (float) $stock) {
                    $validator->errors()->add('current_stock', "Unit '{$unit->name}' does not allow decimal quantities for stock.");
                }

                $alert = $this->input('stock_alert_threshold');
                if ($alert !== null && floor((float) $alert) != (float) $alert) {
                    $validator->errors()->add('stock_alert_threshold', "Unit '{$unit->name}' does not allow decimal quantities for alert threshold.");
                }
            }
        });
    }
}
