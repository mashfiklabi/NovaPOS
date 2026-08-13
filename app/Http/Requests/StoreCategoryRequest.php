<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where(function ($query) {
                    return $query->where('parent_id', $this->input('parent_id'));
                }),
            ],
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:categories,id',
            'status' => 'required|string|in:active,inactive',
        ];
    }
}
