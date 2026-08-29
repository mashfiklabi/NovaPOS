<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete');
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->can('customers.restore');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can('customers.bulk_delete');
    }

    public function bulkRestore(User $user): bool
    {
        return $user->can('customers.bulk_restore');
    }
}
