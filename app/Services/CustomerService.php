<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * Create a new customer.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            return Customer::create($data);
        });
    }

    /**
     * Update an existing customer.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $customer->update($data);

            return $customer;
        });
    }

    /**
     * Soft delete a customer.
     */
    public function delete(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer->delete();
        });
    }

    /**
     * Restore a soft deleted customer.
     */
    public function restore(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer->restore();
        });
    }

    /**
     * Bulk soft delete customers.
     *
     * @param  array<int>  $ids
     */
    public function bulkDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $customer = Customer::find($id);
                if ($customer) {
                    $this->delete($customer);
                }
            }
        });
    }

    /**
     * Bulk restore soft deleted customers.
     *
     * @param  array<int>  $ids
     */
    public function bulkRestore(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $customers = Customer::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($customers as $customer) {
                $customer->restore();
            }
        });
    }
}
