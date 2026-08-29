<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkDestroySaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulkDelete', Sale::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:sales,id'],
        ];
    }
}
