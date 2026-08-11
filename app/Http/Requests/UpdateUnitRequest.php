<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('unit'));
    }

    public function rules(): array
    {
        $unitId = $this->route('unit')->id;

        return [
            'name' => "required|string|max:255|unique:units,name,{$unitId}",
            'short_name' => "required|string|max:50|unique:units,short_name,{$unitId}",
            'allow_decimal' => 'required|string|in:allowed,disallowed',
        ];
    }
}
