<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('units.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('units.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('units.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('units.delete');
    }

    public function restore(User $user): bool
    {
        return $user->hasPermissionTo('units.delete');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPermissionTo('units.delete');
    }

    public function bulkRestore(User $user): bool
    {
        return $user->hasPermissionTo('units.delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo('units.view');
    }
}
