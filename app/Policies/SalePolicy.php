<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->can('sales.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->can('sales.update');
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->can('sales.delete');
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $user->can('sales.cancel');
    }

    public function pay(User $user, Sale $sale): bool
    {
        return $user->can('sales.payment');
    }

    public function restore(User $user, Sale $sale): bool
    {
        return $user->can('sales.restore');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can('sales.bulk_delete');
    }

    public function bulkRestore(User $user): bool
    {
        return $user->can('sales.bulk_restore');
    }
}
