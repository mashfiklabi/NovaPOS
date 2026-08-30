<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\BulkDestroySaleRequest;
use App\Http\Requests\BulkRestoreSaleRequest;
use App\Http\Requests\CancelSaleRequest;
use App\Http\Requests\StoreSalePaymentRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $saleService
    ) {}

    /**
     * Display a listing of sales transactions.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sale::class);

        $search = $request->input('search');
        $status = $request->input('status'); // draft, completed, cancelled
        $paymentStatus = $request->input('payment_status'); // unpaid, partial, paid
        $trash = $request->input('trash', 'active'); // active, trash

        $query = Sale::with(['customer', 'cashier']);

        if ($trash === 'trash') {
            $query->onlyTrashed();
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        $sales = $query->when($search, function ($q, $search) {
            $q->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
        })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'trash' => $trash,
            ],
        ]);
    }

    /**
     * Show the form for creating a new sale transaction.
     */
    public function create(): Response
    {
        $this->authorize('create', Sale::class);

        $customers = Customer::where('status', 'active')
            ->select('id', 'name', 'phone')
            ->orderBy('name')
            ->get();

        $products = Product::where('status', 'active')
            ->select('id', 'name', 'sku', 'barcode', 'selling_price', 'current_stock', 'allow_decimal', 'tax_type', 'tax_rate')
            ->with('unit:id,name,short_name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Sales/Create', [
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created sale transaction.
     */
    /**
     * Show the form for editing the specified sale transaction.
     */
    public function edit(Sale $sale): Response
    {
        $this->authorize('update', $sale);

        $sale->load(['customer', 'items.product']);

        $customers = Customer::where('status', 'active')
            ->select('id', 'name', 'phone')
            ->orderBy('name')
            ->get();

        $products = Product::where('status', 'active')
            ->select('id', 'name', 'sku', 'barcode', 'selling_price', 'current_stock', 'allow_decimal', 'tax_type', 'tax_rate')
            ->with('unit:id,name,short_name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Sales/Edit', [
            'sale' => $sale,
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        try {
            $sale = $this->saleService->create($request->validated());

            return redirect()->route('sales.show', $sale->id)
                ->with('success', 'Sale transaction completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified sale transaction details.
     */
    public function show(Sale $sale): Response
    {
        $this->authorize('view', $sale);

        $sale->load(['customer', 'items.product', 'cashier', 'payments.user']);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
        ]);
    }

    /**
     * Update the specified sale transaction.
     */
    public function update(UpdateSaleRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $updated = $this->saleService->update($sale, $request->validated());

            return redirect()->route('sales.show', $updated->id)
                ->with('success', 'Sale updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Record a payment for the specified sale transaction.
     */
    public function pay(StoreSalePaymentRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->saleService->recordPayment($sale, $request->validated());

            return redirect()->back()->with('success', 'Payment recorded successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel the specified sale transaction.
     */
    public function cancel(CancelSaleRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->saleService->cancel($sale);

            return redirect()->back()->with('success', 'Sale cancelled successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified sale transaction.
     */
    public function destroy(Sale $sale): RedirectResponse
    {
        $this->authorize('delete', $sale);

        $this->saleService->delete($sale);

        return redirect()->route('sales.index')->with('success', 'Sale moved to trash successfully.');
    }

    /**
     * Restore the specified soft deleted sale.
     */
    public function restore(int $id): RedirectResponse
    {
        $sale = Sale::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $sale);

        $this->saleService->restore($sale);

        return redirect()->back()->with('success', 'Sale restored successfully.');
    }

    /**
     * Bulk soft delete sales.
     */
    public function bulkDestroy(BulkDestroySaleRequest $request): RedirectResponse
    {
        $this->saleService->bulkDelete($request->validated()['ids']);

        return redirect()->back()->with('success', 'Selected sales moved to trash successfully.');
    }

    /**
     * Bulk restore sales.
     */
    public function bulkRestore(BulkRestoreSaleRequest $request): RedirectResponse
    {
        $this->saleService->bulkRestore($request->validated()['ids']);

        return redirect()->back()->with('success', 'Selected sales restored successfully.');
    }

    /**
     * Export sales as CSV.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Sale::class);

        $headers = [
            'ID',
            'Invoice Number',
            'Customer',
            'Sale Date',
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

        $query = Sale::with('customer')->orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'sales_export_'.now()->format('Y_m_d_His').'.csv',
            $headers,
            $query,
            function (Sale $sale) {
                return [
                    $sale->id,
                    $sale->invoice_number,
                    $sale->customer ? $sale->customer->name : 'Walk-in Customer',
                    $sale->sale_date ? $sale->sale_date->format('Y-m-d') : '',
                    $sale->status->value ?? $sale->status,
                    $sale->payment_status->value ?? $sale->payment_status,
                    $sale->subtotal,
                    $sale->discount_amount,
                    $sale->tax_amount,
                    $sale->shipping_cost,
                    $sale->grand_total,
                    $sale->paid_amount,
                    $sale->due_amount,
                    $sale->created_at ? $sale->created_at->toIso8601String() : '',
                ];
            }
        );
    }
}
