<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SaleRefund;
use Illuminate\Support\Str;

class SaleRefundObserver
{
    public function creating(SaleRefund $refund): void
    {
        if (empty($refund->uuid)) {
            $refund->uuid = (string) Str::uuid();
        }
    }
}
