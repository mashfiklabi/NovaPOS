<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreditLedgerType;
use App\Enums\PaymentStatus;
use App\Enums\SalePaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\CustomerCreditLedger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SaleService
{
    /**
     * Create a new sale with line items and initial payment if provided.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Sale
    {
        $userId = Auth::id();
        if (! $userId) {
            throw new InvalidArgumentException('An authenticated user is required for sales operations.');
        }

        return DB::transaction(function () use ($data, $userId) {
            $itemsData = $data['items'] ?? [];
            unset($data['items']);

            $paymentMethod = $data['payment_method'] ?? SalePaymentMethod::CASH->value;
            unset($data['payment_method']);

            $data['status'] = $data['status'] ?? SaleStatus::COMPLETED->value;
            $data['user_id'] = $userId;

            // Compute financial totals with authoritative server-side product prices
            $totals = $this->calculateTotals($itemsData, $data);
            $data['subtotal'] = $totals['subtotal'];
            $data['discount_amount'] = $totals['discount_amount'];
            $data['tax_amount'] = $totals['tax_amount'];
            $data['shipping_cost'] = $totals['shipping_cost'];
            $data['grand_total'] = $totals['grand_total'];
            $data['paid_amount'] = $totals['paid_amount'];
            $data['due_amount'] = $totals['due_amount'];
            $data['payment_status'] = $totals['payment_status'];

            $sale = Sale::create($data);

            foreach ($totals['items'] as $itemData) {
                $sale->items()->create($itemData);

                // Inventory deduction for tracked products
                $product = Product::find($itemData['product_id']);
                if ($product && $product->track_stock && $data['status'] === SaleStatus::COMPLETED->value) {
                    $product->decrement('current_stock', $itemData['quantity']);
                    $freshProduct = $product->fresh();

                    StockMovement::create([
                        'product_id' => $product->id,
                        'movement_type' => StockMovementType::OUT,
                        'quantity' => -$itemData['quantity'],
                        'reference_type' => Sale::class,
                        'reference_id' => $sale->id,
                        'balance_after' => $freshProduct->current_stock,
                        'created_by' => $userId,
                    ]);
                }
            }

            if ($sale->paid_amount > 0) {
                // If payment method is STORE_CREDIT, handle atomic store credit deduction
                if ($paymentMethod === SalePaymentMethod::STORE_CREDIT->value) {
                    if (! $sale->customer_id) {
                        throw new InvalidArgumentException('Store credit payment requires a selected customer.');
                    }

                    $customer = Customer::where('id', $sale->customer_id)->lockForUpdate()->firstOrFail();
                    $availableCredit = $customer->store_credit_balance;

                    if ($sale->paid_amount > $availableCredit) {
                        throw new InvalidArgumentException("Payment amount (\${$sale->paid_amount}) exceeds customer's available store credit (\${$availableCredit}).");
                    }

                    $newCreditBalance = round($availableCredit - $sale->paid_amount, 2);

                    CustomerCreditLedger::create([
                        'customer_id' => $customer->id,
                        'sale_id' => $sale->id,
                        'type' => CreditLedgerType::DEBIT,
                        'amount' => $sale->paid_amount,
                        'balance_after' => $newCreditBalance,
                        'reference_number' => "POS-{$sale->invoice_number}",
                        'reason' => "Used store credit for Invoice #{$sale->invoice_number}",
                        'created_by' => $userId,
                    ]);

                    activity()
                        ->performedOn($sale)
                        ->causedBy(Auth::user())
                        ->withProperties([
                            'customer_id' => $customer->id,
                            'amount_deducted' => $sale->paid_amount,
                            'remaining_credit' => $newCreditBalance,
                        ])
                        ->log("Used {$sale->paid_amount} store credit for Invoice #{$sale->invoice_number}");
                }

                $sale->payments()->create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'payment_method' => $paymentMethod,
                    'amount' => $sale->paid_amount,
                    'paid_at' => now(),
                    'notes' => $paymentMethod === SalePaymentMethod::STORE_CREDIT->value ? 'Paid via Customer Store Credit' : 'Initial payment at checkout',
                ]);
            }

            NotificationService::notifyAdminsAndManagers(
                'New Sale Completed',
                "Invoice #{$sale->invoice_number} completed totaling \${$sale->grand_total}",
                "/sales/{$sale->id}"
            );

            return $sale->load('items.product', 'customer', 'payments');
        });
    }

    /**
     * Update an existing sale while preserving payment integrity.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Sale $sale, array $data): Sale
    {
        $userId = Auth::id();
        if (! $userId) {
            throw new InvalidArgumentException('An authenticated user is required for sales operations.');
        }

        return DB::transaction(function () use ($sale, $data, $userId) {
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            // Reverse old stock deductions if previous status was COMPLETED
            if ($lockedSale->status === SaleStatus::COMPLETED) {
                foreach ($lockedSale->items as $oldItem) {
                    $product = Product::find($oldItem->product_id);
                    if ($product && $product->track_stock) {
                        $product->increment('current_stock', $oldItem->quantity);
                        $freshProduct = $product->fresh();

                        StockMovement::create([
                            'product_id' => $product->id,
                            'movement_type' => StockMovementType::ADJUSTMENT,
                            'quantity' => $oldItem->quantity,
                            'reference_type' => Sale::class,
                            'reference_id' => $lockedSale->id,
                            'balance_after' => $freshProduct->current_stock,
                            'created_by' => $userId,
                        ]);
                    }
                }
            }

            $itemsData = $data['items'] ?? [];
            unset($data['items']);
            unset($data['payment_method']);

            // Preserve actual payment integrity from existing SalePayment records
            $existingPaidSum = (float) $lockedSale->payments()->sum('amount');
            if ($existingPaidSum === 0.0 && (float) $lockedSale->paid_amount > 0) {
                $existingPaidSum = (float) $lockedSale->paid_amount;
            }
            $data['paid_amount'] = $existingPaidSum;

            $totals = $this->calculateTotals($itemsData, $data);
            $data['subtotal'] = $totals['subtotal'];
            $data['discount_amount'] = $totals['discount_amount'];
            $data['tax_amount'] = $totals['tax_amount'];
            $data['shipping_cost'] = $totals['shipping_cost'];
            $data['grand_total'] = $totals['grand_total'];
            $data['paid_amount'] = $totals['paid_amount'];
            $data['due_amount'] = $totals['due_amount'];
            $data['payment_status'] = $totals['payment_status'];

            $lockedSale->update($data);

            // Re-create items and apply new stock deduction if updated status is COMPLETED
            $lockedSale->items()->delete();
            foreach ($totals['items'] as $itemData) {
                $lockedSale->items()->create($itemData);

                if ($lockedSale->status === SaleStatus::COMPLETED) {
                    $product = Product::find($itemData['product_id']);
                    if ($product && $product->track_stock) {
                        $product->decrement('current_stock', $itemData['quantity']);
                        $freshProduct = $product->fresh();

                        StockMovement::create([
                            'product_id' => $product->id,
                            'movement_type' => StockMovementType::OUT,
                            'quantity' => -$itemData['quantity'],
                            'reference_type' => Sale::class,
                            'reference_id' => $lockedSale->id,
                            'balance_after' => $freshProduct->current_stock,
                            'created_by' => $userId,
                        ]);
                    }
                }
            }

            return $lockedSale->load('items.product', 'customer', 'payments');
        });
    }

    /**
     * Record a payment with row locking and balance verification.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public function recordPayment(Sale $sale, array $paymentData): Sale
    {
        $userId = Auth::id();
        if (! $userId) {
            throw new InvalidArgumentException('An authenticated user is required for sales operations.');
        }

        return DB::transaction(function () use ($sale, $paymentData, $userId) {
            // Lock sale row for concurrency safety
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status === SaleStatus::CANCELLED) {
                throw new InvalidArgumentException('Cannot record payment for a cancelled sale.');
            }

            $amount = (float) ($paymentData['amount'] ?? 0);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }

            // Recalculate true outstanding amount inside locked transaction
            $currentPaidSum = (float) $lockedSale->payments()->sum('amount');
            if ($currentPaidSum === 0.0 && (float) $lockedSale->paid_amount > 0) {
                $currentPaidSum = (float) $lockedSale->paid_amount;
            }
            $currentDue = round((float) $lockedSale->grand_total - $currentPaidSum, 2);
            if ($currentDue < 0) {
                $currentDue = 0.0;
            }

            if ($amount > $currentDue) {
                throw new InvalidArgumentException("Payment amount (\${$amount}) exceeds current outstanding due amount (\${$currentDue}).");
            }

            $newPaid = round($currentPaidSum + $amount, 2);
            $newDue = round((float) $lockedSale->grand_total - $newPaid, 2);
            if ($newDue < 0) {
                $newDue = 0.0;
            }

            $newPaymentStatus = $newDue <= 0 ? PaymentStatus::PAID : PaymentStatus::PARTIAL;

            $lockedSale->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'payment_status' => $newPaymentStatus,
            ]);

            $paymentMethod = $paymentData['payment_method'] ?? SalePaymentMethod::CASH->value;

            if ($paymentMethod === SalePaymentMethod::STORE_CREDIT->value) {
                if (! $lockedSale->customer_id) {
                    throw new InvalidArgumentException('Store credit payment requires a selected customer.');
                }

                $customer = Customer::where('id', $lockedSale->customer_id)->lockForUpdate()->firstOrFail();
                $availableCredit = $customer->store_credit_balance;

                if ($amount > $availableCredit) {
                    throw new InvalidArgumentException("Payment amount (\${$amount}) exceeds customer's available store credit (\${$availableCredit}).");
                }

                $newCreditBalance = round($availableCredit - $amount, 2);

                CustomerCreditLedger::create([
                    'customer_id' => $customer->id,
                    'sale_id' => $lockedSale->id,
                    'type' => CreditLedgerType::DEBIT,
                    'amount' => $amount,
                    'balance_after' => $newCreditBalance,
                    'reference_number' => "PAY-{$lockedSale->invoice_number}",
                    'reason' => "Used store credit for Invoice #{$lockedSale->invoice_number}",
                    'created_by' => $userId,
                ]);

                activity()
                    ->performedOn($lockedSale)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'customer_id' => $customer->id,
                        'amount_deducted' => $amount,
                        'remaining_credit' => $newCreditBalance,
                    ])
                    ->log("Used {$amount} store credit for Invoice #{$lockedSale->invoice_number}");
            }

            $lockedSale->payments()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'reference_number' => $paymentData['reference_number'] ?? null,
                'paid_at' => now(),
                'notes' => $paymentData['notes'] ?? null,
            ]);

            NotificationService::notifyAdminsAndManagers(
                'Sale Payment Recorded',
                "Payment of \${$amount} recorded for Invoice #{$lockedSale->invoice_number}. Remaining due: \${$newDue}",
                "/sales/{$lockedSale->id}"
            );

            return $lockedSale->fresh(['payments', 'customer', 'items']);
        });
    }

    /**
     * Cancel a sale.
     */
    public function cancel(Sale $sale): Sale
    {
        $userId = Auth::id();

        return DB::transaction(function () use ($sale, $userId) {
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status === SaleStatus::CANCELLED) {
                return $lockedSale;
            }

            // Restore stock if cancelling a completed sale
            if ($lockedSale->status === SaleStatus::COMPLETED) {
                foreach ($lockedSale->items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product && $product->track_stock) {
                        $product->increment('current_stock', $item->quantity);
                        $freshProduct = $product->fresh();

                        StockMovement::create([
                            'product_id' => $product->id,
                            'movement_type' => StockMovementType::ADJUSTMENT,
                            'quantity' => $item->quantity,
                            'reference_type' => Sale::class,
                            'reference_id' => $lockedSale->id,
                            'balance_after' => $freshProduct->current_stock,
                            'created_by' => $userId,
                        ]);
                    }
                }
            }

            $lockedSale->update([
                'status' => SaleStatus::CANCELLED,
            ]);

            NotificationService::notifyAdminsAndManagers(
                'Sale Cancelled',
                "Invoice #{$lockedSale->invoice_number} was CANCELLED.",
                "/sales/{$lockedSale->id}"
            );

            return $lockedSale;
        });
    }

    /**
     * Soft delete a sale.
     */
    public function delete(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale->delete();
        });
    }

    /**
     * Restore a soft deleted sale.
     */
    public function restore(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale->restore();
        });
    }

    /**
     * Bulk soft delete sales.
     *
     * @param  array<int>  $ids
     */
    public function bulkDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $sale = Sale::find($id);
                if ($sale) {
                    $this->delete($sale);
                }
            }
        });
    }

    /**
     * Bulk restore soft deleted sales.
     *
     * @param  array<int>  $ids
     */
    public function bulkRestore(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $sales = Sale::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($sales as $sale) {
                $sale->restore();
            }
        });
    }

    /**
     * Helper to compute calculation details and format item data.
     * Uses authoritative product prices from the database.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @param  array<string, mixed>  $headerData
     * @return array<string, mixed>
     */
    protected function calculateTotals(array $rawItems, array $headerData): array
    {
        $productIds = array_column($rawItems, 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $processedItems = [];
        $subtotal = 0.0;

        foreach ($rawItems as $item) {
            $productId = $item['product_id'];
            $product = $products->get($productId);

            if (! $product) {
                throw new InvalidArgumentException("Product ID {$productId} not found.");
            }

            $qty = (float) ($item['quantity'] ?? 0);
            // Authoritative selling price from DB product record
            $unitPrice = (float) $product->selling_price;

            $discount = (float) ($item['discount_amount'] ?? $item['discount'] ?? 0);
            $tax = (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);

            $unitId = $item['unit_id'] ?? $product->unit_id;

            $lineSubtotal = round($qty * $unitPrice, 2);
            $lineTotal = round($lineSubtotal - $discount + $tax, 2);
            if ($lineTotal < 0) {
                $lineTotal = 0.0;
            }

            $subtotal += $lineTotal;

            $processedItems[] = [
                'product_id' => $productId,
                'unit_id' => $unitId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'subtotal' => $lineSubtotal,
                'total' => $lineTotal,
            ];
        }

        $headerDiscount = (float) ($headerData['discount_amount'] ?? 0);
        $headerTax = (float) ($headerData['tax_amount'] ?? 0);
        $shipping = (float) ($headerData['shipping_cost'] ?? 0);
        $paid = (float) ($headerData['paid_amount'] ?? 0);

        $grandTotal = round($subtotal - $headerDiscount + $headerTax + $shipping, 2);
        if ($grandTotal < 0) {
            $grandTotal = 0.0;
        }

        if ($paid > $grandTotal) {
            throw new InvalidArgumentException("Initial paid amount (\${$paid}) cannot exceed sale grand total (\${$grandTotal}).");
        }

        $dueAmount = round($grandTotal - $paid, 2);
        if ($dueAmount < 0) {
            $dueAmount = 0.0;
        }

        if ($paid <= 0) {
            $paymentStatus = PaymentStatus::UNPAID;
        } elseif ($paid >= $grandTotal) {
            $paymentStatus = PaymentStatus::PAID;
        } else {
            $paymentStatus = PaymentStatus::PARTIAL;
        }

        return [
            'items' => $processedItems,
            'subtotal' => round($subtotal, 2),
            'discount_amount' => $headerDiscount,
            'tax_amount' => $headerTax,
            'shipping_cost' => $shipping,
            'grand_total' => $grandTotal,
            'paid_amount' => $paid,
            'due_amount' => $dueAmount,
            'payment_status' => $paymentStatus,
        ];
    }
}
