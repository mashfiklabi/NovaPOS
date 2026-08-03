<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.view');
    }

    /**
     * Determine whether the user can create a user.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.create');
    }

    /**
     * Determine whether the user can update a user.
     */
    public function update(User $user): bool
    {
        return $user->hasPermissionTo('users.update');
    }

    /**
     * Determine whether the user can delete a user.
     */
    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('users.delete');
    }
}
