<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\BulkDestroyCustomerRequest;
use App\Http\Requests\BulkRestoreCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Display a listing of customers.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $search = $request->input('search');
        $status = $request->input('status', 'active'); // active, trash

        $query = Customer::query();

        if ($status === 'trash') {
            $query->onlyTrashed();
        }

        $customers = $query->when($search, function ($q, $search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        })
            ->with(['creditLedgers' => function ($lq) {
                $lq->orderBy('id', 'desc')->take(10);
            }])
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $customers->getCollection()->transform(function ($customer) {
            $customer->store_credit_balance = $customer->store_credit_balance;

            return $customer;
        });

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customerService->create($request->validated());

        return redirect()->back()
            ->with('success', 'Customer created successfully.')
            ->with('created_customer_id', $customer->id);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customerService->update($customer, $request->validated());

        return redirect()->back()->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->customerService->delete($customer);

        return redirect()->back()->with('success', 'Customer deleted successfully.');
    }

    /**
     * Restore the specified soft deleted customer.
     */
    public function restore(int $id): RedirectResponse
    {
        $customer = Customer::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $customer);

        $this->customerService->restore($customer);

        return redirect()->back()->with('success', 'Customer restored successfully.');
    }

    /**
     * Bulk soft delete customers.
     */
    public function bulkDestroy(BulkDestroyCustomerRequest $request): RedirectResponse
    {
        $this->customerService->bulkDelete($request->validated()['ids']);

        return redirect()->back()->with('success', 'Selected customers deleted successfully.');
    }

    /**
     * Bulk restore soft deleted customers.
     */
    public function bulkRestore(BulkRestoreCustomerRequest $request): RedirectResponse
    {
        $this->customerService->bulkRestore($request->validated()['ids']);

        return redirect()->back()->with('success', 'Selected customers restored successfully.');
    }

    /**
     * Export customers as CSV.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Customer::class);

        $headers = [
            'ID',
            'Name',
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

        $query = Customer::orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'customers_export_'.now()->format('Y_m_d_His').'.csv',
            $headers,
            $query,
            function (Customer $customer) {
                return [
                    $customer->id,
                    $customer->name,
                    $customer->email ?? '',
                    $customer->phone ?? '',
                    $customer->address ?? '',
                    $customer->city ?? '',
                    $customer->state ?? '',
                    $customer->postal_code ?? '',
                    $customer->country ?? '',
                    $customer->tax_number ?? '',
                    $customer->status,
                    $customer->created_at ? $customer->created_at->toIso8601String() : '',
                ];
            }
        );
    }
}
