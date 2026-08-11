<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    /**
     * Create a new product.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($data, $image) {
            if ($image) {
                $data['image'] = $image->store('products', 'public');
            }

            return Product::create($data);
        });
    }

    /**
     * Update an existing product.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($product, $data, $image) {
            if ($image) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $data['image'] = $image->store('products', 'public');
            }
            $product->update($data);

            return $product;
        });
    }

    /**
     * Soft delete a product.
     */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->delete();
        });
    }
}
