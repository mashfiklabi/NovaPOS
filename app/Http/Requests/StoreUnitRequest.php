<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Unit::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:units,name',
            'short_name' => 'required|string|max:50|unique:units,short_name',
            'allow_decimal' => 'required|string|in:allowed,disallowed',
        ];
    }
}
