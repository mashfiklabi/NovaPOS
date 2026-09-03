<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditLedgerType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'invoice_number',
        'customer_id',
        'user_id',
        'sale_date',
        'reference_number',
        'notes',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_cost',
        'grand_total',
        'paid_amount',
        'due_amount',
        'payment_status',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'payment_status' => PaymentStatus::class,
        'status' => SaleStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SaleRefund::class);
    }

    public function creditLedgers(): HasMany
    {
        return $this->hasMany(CustomerCreditLedger::class);
    }

    public function getSettledAmountAttribute(): float
    {
        $refundedSum = (float) $this->refunds()->where('status', RefundStatus::COMPLETED)->sum('amount');
        $creditedSum = (float) $this->creditLedgers()->where('type', CreditLedgerType::CREDIT)->sum('amount');

        return round($refundedSum + $creditedSum, 2);
    }

    public function getEligibleSettlementAmountAttribute(): float
    {
        $paid = (float) $this->paid_amount;
        $settled = $this->settled_amount;

        $remaining = round($paid - $settled, 2);

        return $remaining > 0 ? $remaining : 0.0;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
