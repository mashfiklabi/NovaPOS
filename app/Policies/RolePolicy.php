<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('roles.view');
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('roles.create');
    }

    /**
     * Determine whether the user can update roles.
     */
    public function update(User $user): bool
    {
        return $user->hasPermissionTo('roles.update');
    }

    /**
     * Determine whether the user can delete roles.
     */
    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('roles.delete');
    }
}
