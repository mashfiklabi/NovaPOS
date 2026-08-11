<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('products.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('products.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('products.delete');
    }

    public function restore(User $user): bool
    {
        return $user->hasPermissionTo('products.delete');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPermissionTo('products.delete');
    }

    public function bulkRestore(User $user): bool
    {
        return $user->hasPermissionTo('products.delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo('products.view');
    }
}
