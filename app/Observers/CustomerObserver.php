<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CustomerObserver
{
    public function creating(Customer $customer): void
    {
        if (empty($customer->uuid)) {
            $customer->uuid = (string) Str::uuid();
        }

        if (Auth::check() && empty($customer->created_by)) {
            $customer->created_by = Auth::id();
        }
    }

    public function updating(Customer $customer): void
    {
        if (Auth::check()) {
            $customer->updated_by = Auth::id();
        }
    }

    public function deleting(Customer $customer): void
    {
        if (Auth::check()) {
            $customer->deleted_by = Auth::id();
            $customer->saveQuietly();
        }
    }
}
