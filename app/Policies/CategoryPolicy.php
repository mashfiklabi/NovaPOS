<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('categories.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('categories.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('categories.delete');
    }

    public function restore(User $user): bool
    {
        return $user->hasPermissionTo('categories.restore');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPermissionTo('categories.bulk_delete');
    }

    public function bulkRestore(User $user): bool
    {
        return $user->hasPermissionTo('categories.bulk_restore');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo('categories.export');
    }
}
