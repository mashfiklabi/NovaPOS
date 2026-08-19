<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('suppliers.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('suppliers.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('suppliers.delete');
    }

    public function restore(User $user): bool
    {
        return $user->hasPermissionTo('suppliers.restore');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPermissionTo('suppliers.bulk_delete');
    }

    public function bulkRestore(User $user): bool
    {
        return $user->hasPermissionTo('suppliers.bulk_restore');
    }
}
