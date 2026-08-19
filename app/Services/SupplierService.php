<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    /**
     * Create a new supplier.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            return Supplier::create($data);
        });
    }

    /**
     * Update an existing supplier.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data) {
            $supplier->update($data);

            return $supplier;
        });
    }

    /**
     * Soft delete a supplier.
     */
    public function delete(Supplier $supplier): void
    {
        DB::transaction(function () use ($supplier) {
            $supplier->delete();
        });
    }

    /**
     * Restore a soft deleted supplier.
     */
    public function restore(Supplier $supplier): void
    {
        DB::transaction(function () use ($supplier) {
            $supplier->restore();
        });
    }

    /**
     * Bulk soft delete suppliers.
     *
     * @param  array<int>  $ids
     */
    public function bulkDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $supplier = Supplier::find($id);
                if ($supplier) {
                    $this->delete($supplier);
                }
            }
        });
    }

    /**
     * Bulk restore soft deleted suppliers.
     *
     * @param  array<int>  $ids
     */
    public function bulkRestore(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $suppliers = Supplier::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($suppliers as $supplier) {
                $supplier->restore();
            }
        });
    }
}
