<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class BulkRestoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bulkRestore', Product::class);
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:products,id',
        ];
    }
}
