<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SettingUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('manage_settings');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:1000',
            'currency' => 'required|string|max:10',
            'invoice_prefix' => 'required|string|max:15',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'timezone' => 'required|string|max:100',
        ];
    }
}
