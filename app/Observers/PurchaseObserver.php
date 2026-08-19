<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Purchase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PurchaseObserver
{
    public function creating(Purchase $purchase): void
    {
        if (empty($purchase->uuid)) {
            $purchase->uuid = (string) Str::uuid();
        }

        if (empty($purchase->po_number)) {
            $prefix = 'PO-'.now()->format('Ym').'-';
            $latest = Purchase::withTrashed()
                ->where('po_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();

            if ($latest) {
                $number = (int) Str::afterLast($latest->po_number, $prefix) + 1;
            } else {
                $number = 1;
            }

            $purchase->po_number = $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        }

        if (Auth::check() && empty($purchase->created_by)) {
            $purchase->created_by = Auth::id();
        }
    }

    public function updating(Purchase $purchase): void
    {
        if (Auth::check()) {
            $purchase->updated_by = Auth::id();
        }
    }

    public function deleting(Purchase $purchase): void
    {
        if (Auth::check()) {
            $purchase->deleted_by = Auth::id();
            $purchase->saveQuietly();
        }
    }
}
