<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreditLedgerType;
use App\Enums\RefundStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\CustomerCreditLedger;
use App\Models\Sale;
use App\Models\SaleRefund;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleRefundService
{
    public function processRefund(Sale $sale, array $data): SaleRefund
    {
        $userId = Auth::id();
        if (! $userId) {
            throw new \InvalidArgumentException('Authenticated user required.');
        }

        return DB::transaction(function () use ($sale, $data, $userId) {
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status !== SaleStatus::CANCELLED) {
                throw new \InvalidArgumentException('Refunds can only be processed for cancelled sales.');
            }

            $requestedAmount = round((float) ($data['amount'] ?? 0), 2);
            if ($requestedAmount <= 0) {
                throw new \InvalidArgumentException('Refund amount must be greater than zero.');
            }

            // Calculate current eligible settlement amount
            $eligible = $lockedSale->eligible_settlement_amount;
            if ($requestedAmount > $eligible) {
                throw new \InvalidArgumentException("Requested refund amount ({$requestedAmount}) exceeds eligible settlement balance ({$eligible}).");
            }

            // Verify company refund policy settings
            $settingService = app(SettingService::class);
            $refundEnabled = filter_var($settingService->get('refund_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
            if (! $refundEnabled) {
                throw new \InvalidArgumentException('Refunds are disabled by company policy.');
            }

            $policyPercentage = (float) $settingService->get('refund_percentage', '100');
            $maxPolicyAmount = round(((float) $lockedSale->paid_amount) * ($policyPercentage / 100), 2);

            $alreadyRefunded = (float) $lockedSale->refunds()->where('status', RefundStatus::COMPLETED)->sum('amount');
            $maxAllowedByPolicy = round($maxPolicyAmount - $alreadyRefunded, 2);
            if ($maxAllowedByPolicy < 0) {
                $maxAllowedByPolicy = 0.0;
            }

            if ($requestedAmount > $maxAllowedByPolicy) {
                throw new \InvalidArgumentException("Requested refund amount ({$requestedAmount}) exceeds company refund policy limit ({$maxAllowedByPolicy}).");
            }

            $refund = SaleRefund::create([
                'sale_id' => $lockedSale->id,
                'customer_id' => $lockedSale->customer_id,
                'amount' => $requestedAmount,
                'refund_method' => $data['refund_method'] ?? 'cash',
                'reason' => $data['reason'] ?? 'Cancelled Sale Refund',
                'reference_number' => $data['reference_number'] ?? null,
                'status' => RefundStatus::COMPLETED,
                'processed_by' => $userId,
                'processed_at' => now(),
            ]);

            activity()
                ->performedOn($refund)
                ->causedBy(Auth::user())
                ->withProperties([
                    'sale_id' => $lockedSale->id,
                    'invoice_number' => $lockedSale->invoice_number,
                    'amount' => $requestedAmount,
                    'refund_method' => $refund->refund_method,
                ])
                ->log("Processed refund of {$requestedAmount} for Invoice #{$lockedSale->invoice_number}");

            NotificationService::notifyAdminsAndManagers(
                'Sale Refund Processed',
                "Refund of \${$requestedAmount} processed for Invoice #{$lockedSale->invoice_number}",
                "/sales/{$lockedSale->id}"
            );

            return $refund;
        });
    }

    public function convertToStoreCredit(Sale $sale, array $data): CustomerCreditLedger
    {
        $userId = Auth::id();
        if (! $userId) {
            throw new \InvalidArgumentException('Authenticated user required.');
        }

        return DB::transaction(function () use ($sale, $data, $userId) {
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status !== SaleStatus::CANCELLED) {
                throw new \InvalidArgumentException('Store credit conversion can only be performed for cancelled sales.');
            }

            if (! $lockedSale->customer_id) {
                throw new \InvalidArgumentException('Store credit cannot be issued for walk-in sales without a customer record.');
            }

            $requestedAmount = round((float) ($data['amount'] ?? 0), 2);
            if ($requestedAmount <= 0) {
                throw new \InvalidArgumentException('Credit amount must be greater than zero.');
            }

            $eligible = $lockedSale->eligible_settlement_amount;
            if ($requestedAmount > $eligible) {
                throw new \InvalidArgumentException("Requested credit amount ({$requestedAmount}) exceeds eligible settlement balance ({$eligible}).");
            }

            $settingService = app(SettingService::class);
            $creditEnabled = filter_var($settingService->get('store_credit_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
            if (! $creditEnabled) {
                throw new \InvalidArgumentException('Store credit feature is disabled by company policy.');
            }

            $customer = Customer::where('id', $lockedSale->customer_id)->lockForUpdate()->firstOrFail();
            $currentBalance = $customer->store_credit_balance;
            $newBalance = round($currentBalance + $requestedAmount, 2);

            $ledger = CustomerCreditLedger::create([
                'customer_id' => $customer->id,
                'sale_id' => $lockedSale->id,
                'type' => CreditLedgerType::CREDIT,
                'amount' => $requestedAmount,
                'balance_after' => $newBalance,
                'reference_number' => "CANCEL-{$lockedSale->invoice_number}",
                'reason' => $data['reason'] ?? "Store credit for cancelled Invoice #{$lockedSale->invoice_number}",
                'created_by' => $userId,
            ]);

            activity()
                ->performedOn($ledger)
                ->causedBy(Auth::user())
                ->withProperties([
                    'sale_id' => $lockedSale->id,
                    'customer_id' => $customer->id,
                    'amount' => $requestedAmount,
                    'new_balance' => $newBalance,
                ])
                ->log("Issued store credit of {$requestedAmount} to Customer #{$customer->id} from Invoice #{$lockedSale->invoice_number}");

            NotificationService::notifyAdminsAndManagers(
                'Store Credit Issued',
                "Issued store credit of \${$requestedAmount} to {$customer->name} from Invoice #{$lockedSale->invoice_number}",
                '/customers'
            );

            return $ledger;
        });
    }
}
