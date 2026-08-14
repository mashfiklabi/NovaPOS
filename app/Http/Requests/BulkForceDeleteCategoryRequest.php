<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class BulkForceDeleteCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bulkDelete', Category::class);
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:categories,id',
        ];
    }
}
