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

    /**
     * Restore a soft deleted category.
     */
    public function restore(Category $category): void
    {
        DB::transaction(function () use ($category) {
            $category->restore();
        });
    }

    /**
     * Force delete a category.
     */
    public function forceDelete(Category $category): void
    {
        if ($category->children()->count() > 0) {
            throw new \InvalidArgumentException('Cannot permanently delete a category containing subcategories.');
        }

        if ($category->products()->count() > 0) {
            throw new \InvalidArgumentException('Cannot permanently delete a category containing associated products.');
        }

        DB::transaction(function () use ($category) {
            $category->forceDelete();
        });
    }

    /**
     * Bulk soft delete categories.
     *
     * @param  array<int>  $ids
     */
    public function bulkDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $category = Category::find($id);
                if ($category) {
                    $this->delete($category);
                }
            }
        });
    }

    /**
     * Bulk restore soft deleted categories.
     *
     * @param  array<int>  $ids
     */
    public function bulkRestore(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $categories = Category::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($categories as $category) {
                $category->restore();
            }
        });
    }

    /**
     * Bulk force delete categories.
     *
     * @param  array<int>  $ids
     */
    public function bulkForceDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $categories = Category::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($categories as $category) {
                $this->forceDelete($category);
            }
        });
    }
}
