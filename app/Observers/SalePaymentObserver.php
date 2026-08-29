<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SalePayment;
use Illuminate\Support\Str;

class SalePaymentObserver
{
    public function creating(SalePayment $payment): void
    {
        if (empty($payment->uuid)) {
            $payment->uuid = (string) Str::uuid();
        }

        if (empty($payment->paid_at)) {
            $payment->paid_at = now();
        }
    }
}
