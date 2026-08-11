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
}
