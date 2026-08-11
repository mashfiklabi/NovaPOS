<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')->id;

        return [
            'name' => "required|string|max:255|unique:categories,name,{$categoryId}",
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($categoryId) {
                    if ((int) $value === (int) $categoryId) {
                        $fail('A category cannot be its own parent.');
                    }
                },
            ],
            'status' => 'required|string|in:active,inactive',
        ];
    }
}
