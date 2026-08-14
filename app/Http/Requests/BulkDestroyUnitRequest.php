<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bulkDelete', Unit::class);
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:units,id,deleted_at,NULL',
        ];
    }
}
