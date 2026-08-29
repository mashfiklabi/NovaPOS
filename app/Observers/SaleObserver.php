<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SaleObserver
{
    public function creating(Sale $sale): void
    {
        if (empty($sale->uuid)) {
            $sale->uuid = (string) Str::uuid();
        }

        if (empty($sale->invoice_number)) {
            $prefix = Setting::where('key', 'invoice_prefix')->value('value') ?? 'INV-';
            $prefixWithMonth = $prefix.now()->format('Ym').'-';

            $latest = Sale::withTrashed()
                ->where('invoice_number', 'like', "{$prefixWithMonth}%")
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            if ($latest) {
                $number = (int) Str::afterLast($latest->invoice_number, $prefixWithMonth) + 1;
            } else {
                $number = 1;
            }

            $sale->invoice_number = $prefixWithMonth.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        }

        if (Auth::check() && empty($sale->created_by)) {
            $sale->created_by = Auth::id();
        }

        if (Auth::check() && empty($sale->user_id)) {
            $sale->user_id = Auth::id();
        }
    }

    public function updating(Sale $sale): void
    {
        if (Auth::check()) {
            $sale->updated_by = Auth::id();
        }
    }

    public function deleting(Sale $sale): void
    {
        if (Auth::check()) {
            $sale->deleted_by = Auth::id();
            $sale->saveQuietly();
        }
    }
}
