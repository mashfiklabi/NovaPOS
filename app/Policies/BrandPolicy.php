<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('brands.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('brands.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('brands.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('brands.delete');
    }
}
