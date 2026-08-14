<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BrandService
{
    /**
     * Create a new brand.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $logo = null): Brand
    {
        return DB::transaction(function () use ($data, $logo) {
            if ($logo) {
                $data['logo'] = $logo->store('brands', 'local');
            }

            return Brand::create($data);
        });
    }

    /**
     * Update an existing brand.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Brand $brand, array $data, ?UploadedFile $logo = null): Brand
    {
        return DB::transaction(function () use ($brand, $data, $logo) {
            $oldLogo = $brand->logo;

            if ($logo) {
                $data['logo'] = $logo->store('brands', 'local');

                // Delete old logo safely after transaction commits successfully
                if ($oldLogo) {
                    DB::afterCommit(function () use ($oldLogo) {
                        Storage::disk('local')->delete($oldLogo);
                    });
                }
            }
            $brand->update($data);

            return $brand;
        });
    }

    /**
     * Soft delete a brand.
     */
    public function delete(Brand $brand): void
    {
        if ($brand->products()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete a brand containing associated products.');
        }

        DB::transaction(function () use ($brand) {
            $brand->delete();
        });
    }

    /**
     * Restore a soft deleted brand.
     */
    public function restore(Brand $brand): void
    {
        DB::transaction(function () use ($brand) {
            $brand->restore();
        });
    }

    /**
     * Force delete a brand and delete its logo.
     */
    public function forceDelete(Brand $brand): void
    {
        if ($brand->products()->count() > 0) {
            throw new \InvalidArgumentException('Cannot permanently delete a brand containing associated products.');
        }

        DB::transaction(function () use ($brand) {
            $logo = $brand->logo;
            if ($logo) {
                DB::afterCommit(function () use ($logo) {
                    Storage::disk('local')->delete($logo);
                });
            }
            $brand->forceDelete();
        });
    }

    /**
     * Bulk soft delete brands.
     *
     * @param  array<int>  $ids
     */
    public function bulkDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $brand = Brand::find($id);
                if ($brand) {
                    $this->delete($brand);
                }
            }
        });
    }

    /**
     * Bulk restore soft deleted brands.
     *
     * @param  array<int>  $ids
     */
    public function bulkRestore(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $brands = Brand::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($brands as $brand) {
                $brand->restore();
            }
        });
    }

    /**
     * Bulk force delete brands.
     *
     * @param  array<int>  $ids
     */
    public function bulkForceDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $brands = Brand::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($brands as $brand) {
                $this->forceDelete($brand);
            }
        });
    }
}
