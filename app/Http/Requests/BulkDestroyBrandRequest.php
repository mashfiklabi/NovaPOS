<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bulkDelete', Brand::class);
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:brands,id,deleted_at,NULL',
        ];
    }
}
