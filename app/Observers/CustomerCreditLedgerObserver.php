<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CustomerCreditLedger;
use Illuminate\Support\Str;

class CustomerCreditLedgerObserver
{
    public function creating(CustomerCreditLedger $ledger): void
    {
        if (empty($ledger->uuid)) {
            $ledger->uuid = (string) Str::uuid();
        }
    }
}
