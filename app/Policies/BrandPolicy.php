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

    public function restore(User $user): bool
    {
        return $user->hasPermissionTo('brands.restore');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPermissionTo('brands.bulk_delete');
    }

    public function bulkRestore(User $user): bool
    {
        return $user->hasPermissionTo('brands.bulk_restore');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo('brands.export');
    }
}
