<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryObserver
{
    /**
     * Handle the Category "creating" event.
     */
    public function creating(Category $category): void
    {
        if (empty($category->uuid)) {
            $category->uuid = (string) Str::uuid();
        }

        if (empty($category->slug)) {
            $category->slug = Str::slug($category->name);
        }

        if (auth()->check()) {
            $category->created_by = auth()->id();
        }
    }

    /**
     * Handle the Category "updating" event.
     */
    public function updating(Category $category): void
    {
        if ($category->isDirty('name')) {
            $category->slug = Str::slug($category->name);
        }

        if (auth()->check()) {
            $category->updated_by = auth()->id();
        }
    }

    /**
     * Handle the Category "deleting" event.
     */
    public function deleting(Category $category): void
    {
        if (auth()->check() && method_exists($category, 'isForceDeleting') && ! $category->isForceDeleting()) {
            $category->deleted_by = auth()->id();
            $category->saveQuietly();
        }
    }
}
