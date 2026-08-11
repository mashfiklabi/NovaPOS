<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('brand'));
    }

    public function rules(): array
    {
        $brandId = $this->route('brand')->id;

        return [
            'name' => "required|string|max:255|unique:brands,name,{$brandId}",
            'description' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
            'status' => 'required|string|in:active,inactive',
        ];
    }
}
