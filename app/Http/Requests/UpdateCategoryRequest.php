<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where(function ($query) {
                    return $query->where('parent_id', $this->input('parent_id'));
                })->ignore($categoryId),
            ],
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($categoryId) {
                    if ((int) $value === (int) $categoryId) {
                        $fail('A category cannot be its own parent.');

                        return;
                    }

                    // Traverse proposed parent's ancestry tree to check for circular reference
                    $parent = Category::find($value);
                    while ($parent) {
                        if ((int) $parent->id === (int) $categoryId) {
                            $fail('Circular parenting detected. Selected parent forms a loop.');

                            return;
                        }
                        $parent = $parent->parent;
                    }
                },
            ],
            'status' => 'required|string|in:active,inactive',
        ];
    }
}
