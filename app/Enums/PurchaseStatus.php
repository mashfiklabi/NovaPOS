<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseStatus: string
{
    case DRAFT = 'draft';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';
}
