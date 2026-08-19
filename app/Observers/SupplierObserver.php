<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupplierObserver
{
    public function creating(Supplier $supplier): void
    {
        if (empty($supplier->uuid)) {
            $supplier->uuid = (string) Str::uuid();
        }

        if (Auth::check() && empty($supplier->created_by)) {
            $supplier->created_by = Auth::id();
        }
    }

    public function updating(Supplier $supplier): void
    {
        if (Auth::check()) {
            $supplier->updated_by = Auth::id();
        }
    }

    public function deleting(Supplier $supplier): void
    {
        if (Auth::check()) {
            $supplier->deleted_by = Auth::id();
            $supplier->saveQuietly();
        }
    }
}
