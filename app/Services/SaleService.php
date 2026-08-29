<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SalePaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
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
        return DB::transaction(function () use ($data) {
            $itemsData = $data['items'] ?? [];
            unset($data['items']);

            $paymentMethod = $data['payment_method'] ?? SalePaymentMethod::CASH->value;
            unset($data['payment_method']);

            $data['status'] = $data['status'] ?? SaleStatus::COMPLETED->value;
            $data['user_id'] = $data['user_id'] ?? Auth::id() ?? 1;

            // Compute financial totals
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
            }

            if ($sale->paid_amount > 0) {
                $sale->payments()->create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => Auth::id() ?? $sale->user_id,
                    'payment_method' => $paymentMethod,
                    'amount' => $sale->paid_amount,
                    'paid_at' => now(),
                    'notes' => 'Initial payment at checkout',
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
     * Update an existing sale.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            if ($sale->status === SaleStatus::CANCELLED) {
                throw new InvalidArgumentException('Cannot edit a cancelled sale.');
            }

            $itemsData = $data['items'] ?? [];
            unset($data['items']);
            unset($data['payment_method']);

            $totals = $this->calculateTotals($itemsData, $data);
            $data['subtotal'] = $totals['subtotal'];
            $data['discount_amount'] = $totals['discount_amount'];
            $data['tax_amount'] = $totals['tax_amount'];
            $data['shipping_cost'] = $totals['shipping_cost'];
            $data['grand_total'] = $totals['grand_total'];
            $data['paid_amount'] = $totals['paid_amount'];
            $data['due_amount'] = $totals['due_amount'];
            $data['payment_status'] = $totals['payment_status'];

            $sale->update($data);

            // Re-create items
            $sale->items()->delete();
            foreach ($totals['items'] as $itemData) {
                $sale->items()->create($itemData);
            }

            return $sale->load('items.product', 'customer', 'payments');
        });
    }

    /**
     * Record a payment for an outstanding sale.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public function recordPayment(Sale $sale, array $paymentData): Sale
    {
        return DB::transaction(function () use ($sale, $paymentData) {
            $amount = (float) ($paymentData['amount'] ?? 0);

            if ($amount <= 0) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }

            if ($amount > (float) $sale->due_amount) {
                throw new InvalidArgumentException('Payment amount cannot exceed the balance due.');
            }

            $newPaid = round((float) $sale->paid_amount + $amount, 2);
            $newDue = round((float) $sale->grand_total - $newPaid, 2);
            if ($newDue < 0) {
                $newDue = 0.0;
            }

            $newPaymentStatus = $newDue <= 0 ? PaymentStatus::PAID : PaymentStatus::PARTIAL;

            $sale->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'payment_status' => $newPaymentStatus,
            ]);

            $sale->payments()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => Auth::id() ?? $sale->user_id,
                'payment_method' => $paymentData['payment_method'] ?? SalePaymentMethod::CASH->value,
                'amount' => $amount,
                'reference_number' => $paymentData['reference_number'] ?? null,
                'paid_at' => now(),
                'notes' => $paymentData['notes'] ?? null,
            ]);

            NotificationService::notifyAdminsAndManagers(
                'Sale Payment Recorded',
                "Payment of \${$amount} recorded for Invoice #{$sale->invoice_number}. Remaining due: \${$newDue}",
                "/sales/{$sale->id}"
            );

            return $sale->fresh(['payments', 'customer', 'items']);
        });
    }

    /**
     * Cancel a sale.
     */
    public function cancel(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $sale->update([
                'status' => SaleStatus::CANCELLED,
            ]);

            NotificationService::notifyAdminsAndManagers(
                'Sale Cancelled',
                "Invoice #{$sale->invoice_number} was CANCELLED.",
                "/sales/{$sale->id}"
            );

            return $sale;
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
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @param  array<string, mixed>  $headerData
     * @return array<string, mixed>
     */
    protected function calculateTotals(array $rawItems, array $headerData): array
    {
        $processedItems = [];
        $subtotal = 0.0;

        foreach ($rawItems as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? $item['discount'] ?? 0);
            $tax = (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);

            $product = Product::find($item['product_id']);
            $unitId = $item['unit_id'] ?? ($product ? $product->unit_id : null);

            $lineSubtotal = round($qty * $unitPrice, 2);
            $lineTotal = round($lineSubtotal - $discount + $tax, 2);
            if ($lineTotal < 0) {
                $lineTotal = 0.0;
            }

            $subtotal += $lineTotal;

            $processedItems[] = [
                'product_id' => $item['product_id'],
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
