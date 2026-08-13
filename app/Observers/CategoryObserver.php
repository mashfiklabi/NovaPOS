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

        $baseSlug = empty($category->slug) ? Str::slug($category->name) : Str::slug($category->slug);
        $slug = $baseSlug;
        $count = 1;

        while (Category::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }
        $category->slug = $slug;

        if (auth()->check()) {
            $category->created_by = auth()->id();
        }
    }

    /**
     * Handle the Category "updating" event.
     */
    public function updating(Category $category): void
    {
        if ($category->isDirty('name') || $category->isDirty('slug')) {
            $baseSlug = Str::slug($category->slug ?: $category->name);
            $slug = $baseSlug;
            $count = 1;

            while (Category::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = "{$baseSlug}-{$count}";
                $count++;
            }
            $category->slug = $slug;
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
