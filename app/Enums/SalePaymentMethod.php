<?php

declare(strict_types=1);

namespace App\Enums;

enum SalePaymentMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case STORE_CREDIT = 'store_credit';
    case OTHER = 'other';
}
