<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\BulkDestroySupplierRequest;
use App\Http\Requests\BulkRestoreSupplierRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $supplierService
    ) {}

    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $search = $request->input('search');
        $status = $request->input('status', 'active'); // active, trash

        $query = Supplier::query();

        if ($status === 'trash') {
            $query->onlyTrashed();
        }

        $suppliers = $query->when($search, function ($q, $search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->supplierService->create($request->validated());

        return redirect()->back()->with('success', 'Supplier created successfully.');
    }

    /**
     * Update the specified supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->supplierService->update($supplier, $request->validated());

        return redirect()->back()->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $this->supplierService->delete($supplier);

        return redirect()->back()->with('success', 'Supplier deleted successfully.');
    }

    /**
     * Restore the specified soft deleted supplier.
     */
    public function restore(int $id): RedirectResponse
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $supplier);

        $this->supplierService->restore($supplier);

        return redirect()->back()->with('success', 'Supplier restored successfully.');
    }

    /**
     * Bulk soft delete suppliers.
     */
    public function bulkDestroy(BulkDestroySupplierRequest $request): RedirectResponse
    {
        $this->supplierService->bulkDelete($request->validated()['ids']);

        return redirect()->back()->with('success', 'Selected suppliers deleted successfully.');
    }

    /**
     * Bulk restore soft deleted suppliers.
     */
    public function bulkRestore(BulkRestoreSupplierRequest $request): RedirectResponse
    {
        $this->supplierService->bulkRestore($request->validated()['ids']);

        return redirect()->back()->with('success', 'Selected suppliers restored successfully.');
    }

    /**
     * Export suppliers as CSV.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $headers = [
            'ID',
            'Name',
            'Company Name',
            'Email',
            'Phone',
            'Address',
            'City',
            'State',
            'Postal Code',
            'Country',
            'Tax Number',
            'Status',
            'Created At',
        ];

        $query = Supplier::orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'suppliers_export_'.now()->format('Y_m_d_His').'.csv',
            $headers,
            $query,
            function (Supplier $supplier) {
                return [
                    $supplier->id,
                    $supplier->name,
                    $supplier->company_name ?? '',
                    $supplier->email ?? '',
                    $supplier->phone ?? '',
                    $supplier->address ?? '',
                    $supplier->city ?? '',
                    $supplier->state ?? '',
                    $supplier->postal_code ?? '',
                    $supplier->country ?? '',
                    $supplier->tax_number ?? '',
                    $supplier->status,
                    $supplier->created_at ? $supplier->created_at->toIso8601String() : '',
                ];
            }
        );
    }
}
