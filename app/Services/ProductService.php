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
                $data['image'] = $image->store('products', 'local');
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
            $oldImage = $product->image;

            if ($image) {
                $data['image'] = $image->store('products', 'local');

                // Delete old image safely after transaction commits successfully
                if ($oldImage) {
                    DB::afterCommit(function () use ($oldImage) {
                        Storage::disk('local')->delete($oldImage);
                    });
                }
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

    /**
     * Restore a soft deleted product.
     */
    public function restore(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->restore();
        });
    }

    /**
     * Force delete a product and delete its image.
     */
    public function forceDelete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $image = $product->image;
            if ($image) {
                DB::afterCommit(function () use ($image) {
                    Storage::disk('local')->delete($image);
                });
            }
            $product->forceDelete();
        });
    }

    /**
     * Bulk soft delete products.
     *
     * @param  array<int>  $ids
     */
    public function bulkDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $product = Product::find($id);
                if ($product) {
                    $this->delete($product);
                }
            }
        });
    }

    /**
     * Bulk restore soft deleted products.
     *
     * @param  array<int>  $ids
     */
    public function bulkRestore(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $products = Product::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($products as $product) {
                $product->restore();
            }
        });
    }

    /**
     * Bulk force delete products.
     *
     * @param  array<int>  $ids
     */
    public function bulkForceDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $products = Product::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($products as $product) {
                $this->forceDelete($product);
            }
        });
    }
}
