<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\BulkDestroyPurchaseRequest;
use App\Http\Requests\BulkRestorePurchaseRequest;
use App\Http\Requests\CancelPurchaseRequest;
use App\Http\Requests\ReceivePurchaseRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    /**
     * Display a listing of purchases.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Purchase::class);

        $search = $request->input('search');
        $status = $request->input('status'); // draft, received, cancelled
        $paymentStatus = $request->input('payment_status'); // unpaid, partial, paid
        $trash = $request->input('trash', 'active'); // active, trash

        $query = Purchase::with(['supplier', 'creator']);

        if ($trash === 'trash') {
            $query->onlyTrashed();
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        $purchases = $query->when($search, function ($q, $search) {
            $q->where('po_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
        })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'trash' => $trash,
            ],
        ]);
    }

    /**
     * Show the form for creating a new purchase order.
     */
    public function create(): Response
    {
        $this->authorize('create', Purchase::class);

        $suppliers = Supplier::where('status', 'active')
            ->select('id', 'name', 'company_name')
            ->orderBy('name')
            ->get();

        $products = Product::where('status', 'active')
            ->select('id', 'name', 'sku', 'cost_price', 'current_stock', 'allow_decimal', 'tax_type', 'tax_rate')
            ->orderBy('name')
            ->get();

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created purchase order.
     */
    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->purchaseService->create($request->validated());

        return redirect()->route('purchases.show', $purchase->id)
            ->with('success', 'Purchase order created successfully.');
    }

    /**
     * Display the specified purchase order.
     */
    public function show(Purchase $purchase): Response
    {
        $this->authorize('view', $purchase);

        $purchase->load(['supplier', 'items.product', 'creator', 'updater']);

        return Inertia::render('Purchases/Show', [
            'purchase' => $purchase,
        ]);
    }

    /**
     * Show the form for editing the purchase order.
     */
    public function edit(Purchase $purchase): Response|RedirectResponse
    {
        $this->authorize('update', $purchase);

        if ($purchase->status->value !== 'draft') {
            return redirect()->route('purchases.show', $purchase->id)
                ->with('error', 'Only draft purchase orders can be edited.');
        }

        $purchase->load(['supplier', 'items.product']);

        $suppliers = Supplier::where('status', 'active')
            ->select('id', 'name', 'company_name')
            ->orderBy('name')
            ->get();

        $products = Product::where('status', 'active')
            ->select('id', 'name', 'sku', 'cost_price', 'current_stock', 'allow_decimal', 'tax_type', 'tax_rate')
            ->orderBy('name')
            ->get();

        return Inertia::render('Purchases/Edit', [
            'purchase' => $purchase,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    /**
     * Update the specified purchase order.
     */
    public function update(UpdatePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $updated = $this->purchaseService->update($purchase, $request->validated());

            return redirect()->route('purchases.show', $updated->id)
                ->with('success', 'Purchase order updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Receive the specified purchase order (increment stock).
     */
    public function receive(ReceivePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchaseService->receive($purchase);

            return redirect()->back()->with('success', 'Purchase order received successfully and stock updated.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel the specified purchase order.
     */
    public function cancel(CancelPurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchaseService->cancel($purchase);

            return redirect()->back()->with('success', 'Purchase order cancelled successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified purchase order.
     */
    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->authorize('delete', $purchase);

        $this->purchaseService->delete($purchase);

        return redirect()->route('purchases.index')->with('success', 'Purchase order deleted successfully.');
    }

    /**
     * Restore the specified soft deleted purchase order.
     */
    public function restore(int $id): RedirectResponse
    {
        $purchase = Purchase::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $purchase);

        $this->purchaseService->restore($purchase);

        return redirect()->back()->with('success', 'Purchase order restored successfully.');
    }

    /**
     * Bulk soft delete purchase orders.
     */
    public function bulkDestroy(BulkDestroyPurchaseRequest $request): RedirectResponse
    {
        $this->purchaseService->bulkDelete($request->validated()['ids']);

        return redirect()->back()->with('success', 'Selected purchase orders deleted successfully.');
    }

    /**
     * Bulk restore purchase orders.
     */
    public function bulkRestore(BulkRestorePurchaseRequest $request): RedirectResponse
    {
        $this->purchaseService->bulkRestore($request->validated()['ids']);

        return redirect()->back()->with('success', 'Selected purchase orders restored successfully.');
    }

    /**
     * Export purchases as CSV.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $headers = [
            'ID',
            'PO Number',
            'Supplier',
            'Order Date',
            'Expected Date',
            'Status',
            'Payment Status',
            'Subtotal',
            'Discount',
            'Tax',
            'Shipping Cost',
            'Grand Total',
            'Paid Amount',
            'Due Amount',
            'Created At',
        ];

        $query = Purchase::with('supplier')->orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'purchases_export_'.now()->format('Y_m_d_His').'.csv',
            $headers,
            $query,
            function (Purchase $purchase) {
                return [
                    $purchase->id,
                    $purchase->po_number,
                    $purchase->supplier ? $purchase->supplier->name : '',
                    $purchase->purchase_date ? $purchase->purchase_date->format('Y-m-d') : '',
                    $purchase->expected_delivery_date ? $purchase->expected_delivery_date->format('Y-m-d') : '',
                    $purchase->status->value ?? $purchase->status,
                    $purchase->payment_status->value ?? $purchase->payment_status,
                    $purchase->subtotal,
                    $purchase->discount_amount,
                    $purchase->tax_amount,
                    $purchase->shipping_cost,
                    $purchase->grand_total,
                    $purchase->paid_amount,
                    $purchase->due_amount,
                    $purchase->created_at ? $purchase->created_at->toIso8601String() : '',
                ];
            }
        );
    }
}
