<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('purchases.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('purchases.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('purchases.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('purchases.delete');
    }

    public function restore(User $user): bool
    {
        return $user->hasPermissionTo('purchases.restore');
    }

    public function receive(User $user): bool
    {
        return $user->hasPermissionTo('purchases.receive');
    }

    public function cancel(User $user): bool
    {
        return $user->hasPermissionTo('purchases.cancel');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPermissionTo('purchases.bulk_delete');
    }

    public function bulkRestore(User $user): bool
    {
        return $user->hasPermissionTo('purchases.bulk_restore');
    }
}
