<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseService
{
    /**
     * Create a new purchase order with items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $itemsData = $data['items'] ?? [];
            unset($data['items']);

            $data['status'] = $data['status'] ?? PurchaseStatus::DRAFT->value;

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

            $purchase = Purchase::create($data);

            foreach ($totals['items'] as $itemData) {
                $purchase->items()->create($itemData);
            }

            if ($purchase->status === PurchaseStatus::RECEIVED) {
                $this->processStockForReceivedPurchase($purchase);
            }

            return $purchase->load('items.product', 'supplier');
        });
    }

    /**
     * Update an existing purchase order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            if ($purchase->status === PurchaseStatus::RECEIVED) {
                throw new InvalidArgumentException('Cannot edit a purchase that has already been received.');
            }

            if ($purchase->status === PurchaseStatus::CANCELLED) {
                throw new InvalidArgumentException('Cannot edit a cancelled purchase order.');
            }

            $itemsData = $data['items'] ?? [];
            unset($data['items']);

            $totals = $this->calculateTotals($itemsData, $data);
            $data['subtotal'] = $totals['subtotal'];
            $data['discount_amount'] = $totals['discount_amount'];
            $data['tax_amount'] = $totals['tax_amount'];
            $data['shipping_cost'] = $totals['shipping_cost'];
            $data['grand_total'] = $totals['grand_total'];
            $data['paid_amount'] = $totals['paid_amount'];
            $data['due_amount'] = $totals['due_amount'];
            $data['payment_status'] = $totals['payment_status'];

            $purchase->update($data);

            // Re-create items
            $purchase->items()->delete();
            foreach ($totals['items'] as $itemData) {
                $purchase->items()->create($itemData);
            }

            if ($purchase->status === PurchaseStatus::RECEIVED) {
                $this->processStockForReceivedPurchase($purchase);
            }

            return $purchase->load('items.product', 'supplier');
        });
    }

    /**
     * Mark a purchase order as RECEIVED and increment product stock.
     */
    public function receive(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            if ($purchase->status === PurchaseStatus::RECEIVED) {
                throw new InvalidArgumentException('Purchase order is already received.');
            }

            if ($purchase->status === PurchaseStatus::CANCELLED) {
                throw new InvalidArgumentException('Cannot receive a cancelled purchase order.');
            }

            $purchase->update([
                'status' => PurchaseStatus::RECEIVED,
            ]);

            $this->processStockForReceivedPurchase($purchase);

            return $purchase->fresh(['items.product', 'supplier']);
        });
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            if ($purchase->status === PurchaseStatus::RECEIVED) {
                throw new InvalidArgumentException('Cannot cancel a purchase order that has already been received.');
            }

            $purchase->update([
                'status' => PurchaseStatus::CANCELLED,
            ]);

            return $purchase;
        });
    }

    /**
     * Soft delete a purchase order.
     */
    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase->delete();
        });
    }

    /**
     * Restore a soft deleted purchase order.
     */
    public function restore(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase->restore();
        });
    }

    /**
     * Bulk soft delete purchase orders.
     *
     * @param  array<int>  $ids
     */
    public function bulkDelete(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $purchase = Purchase::find($id);
                if ($purchase) {
                    $this->delete($purchase);
                }
            }
        });
    }

    /**
     * Bulk restore soft deleted purchase orders.
     *
     * @param  array<int>  $ids
     */
    public function bulkRestore(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $purchases = Purchase::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($purchases as $purchase) {
                $purchase->restore();
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
            $unitCost = (float) ($item['unit_cost'] ?? $item['cost_price'] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? $item['discount'] ?? 0);
            $tax = (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);

            $product = Product::find($item['product_id']);
            $unitId = $item['unit_id'] ?? ($product ? $product->unit_id : null);

            $total = ($qty * $unitCost) - $discount + $tax;
            $subtotal += $total;

            $processedItems[] = [
                'product_id' => $item['product_id'],
                'unit_id' => $unitId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total' => round($total, 2),
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

    /**
     * Process stock movements and product stock increments for a received purchase.
     */
    protected function processStockForReceivedPurchase(Purchase $purchase): void
    {
        $purchase->loadMissing('items.product');

        foreach ($purchase->items as $item) {
            /** @var Product|null $product */
            $product = $item->product;
            if (! $product) {
                continue;
            }

            if ($product->track_stock) {
                $product->increment('current_stock', $item->quantity);
                $product->refresh();
            }

            StockMovement::create([
                'product_id' => $product->id,
                'created_by' => Auth::id(),
                'movement_type' => StockMovementType::IN,
                'quantity' => $item->quantity,
                'reference_type' => Purchase::class,
                'reference_id' => $purchase->id,
                'balance_after' => $product->current_stock,
            ]);
        }
    }
}
