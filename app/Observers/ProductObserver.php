<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Str;

class ProductObserver
{
    /**
     * Handle the Product "creating" event.
     */
    public function creating(Product $product): void
    {
        if (empty($product->uuid)) {
            $product->uuid = (string) Str::uuid();
        }

        if (empty($product->slug)) {
            $product->slug = Str::slug($product->name);
        }

        if (auth()->check()) {
            $product->created_by = auth()->id();
        }
    }

    /**
     * Handle the Product "updating" event.
     */
    public function updating(Product $product): void
    {
        if ($product->isDirty('name')) {
            $product->slug = Str::slug($product->name);
        }

        if (auth()->check()) {
            $product->updated_by = auth()->id();
        }
    }

    /**
     * Handle the Product "deleting" event.
     */
    public function deleting(Product $product): void
    {
        if (auth()->check() && method_exists($product, 'isForceDeleting') && ! $product->isForceDeleting()) {
            $product->deleted_by = auth()->id();
            $product->saveQuietly();
        }
    }
}
