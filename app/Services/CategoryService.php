<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    /**
     * Create a new category.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            return Category::create($data);
        });
    }

    /**
     * Update an existing category.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category->update($data);

            return $category;
        });
    }

    /**
     * Soft delete a category.
     */
    public function delete(Category $category): void
    {
        // Prevent deletion of categories with subcategories or products to enforce database integrity.
        if ($category->children()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete a category containing subcategories.');
        }

        if ($category->products()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete a category containing associated products.');
        }

        DB::transaction(function () use ($category) {
            $category->delete();
        });
    }
}
