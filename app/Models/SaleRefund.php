<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sale_id',
        'customer_id',
        'original_payment_id',
        'amount',
        'refund_method',
        'reason',
        'reference_number',
        'status',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => RefundStatus::class,
        'processed_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class, 'original_payment_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
