<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Brand;
use Illuminate\Support\Str;

class BrandObserver
{
    /**
     * Handle the Brand "creating" event.
     */
    public function creating(Brand $brand): void
    {
        if (empty($brand->uuid)) {
            $brand->uuid = (string) Str::uuid();
        }

        $baseSlug = empty($brand->slug) ? Str::slug($brand->name) : Str::slug($brand->slug);
        $slug = $baseSlug;
        $count = 1;

        while (Brand::where('slug', $slug)->where('id', '!=', $brand->id)->exists()) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }
        $brand->slug = $slug;

        if (auth()->check()) {
            $brand->created_by = auth()->id();
        }
    }

    /**
     * Handle the Brand "updating" event.
     */
    public function updating(Brand $brand): void
    {
        if ($brand->isDirty('name') || $brand->isDirty('slug')) {
            $baseSlug = Str::slug($brand->slug ?: $brand->name);
            $slug = $baseSlug;
            $count = 1;

            while (Brand::where('slug', $slug)->where('id', '!=', $brand->id)->exists()) {
                $slug = "{$baseSlug}-{$count}";
                $count++;
            }
            $brand->slug = $slug;
        }

        if (auth()->check()) {
            $brand->updated_by = auth()->id();
        }
    }

    /**
     * Handle the Brand "deleting" event.
     */
    public function deleting(Brand $brand): void
    {
        if (auth()->check() && method_exists($brand, 'isForceDeleting') && ! $brand->isForceDeleting()) {
            $brand->deleted_by = auth()->id();
            $brand->saveQuietly();
        }
    }
}
