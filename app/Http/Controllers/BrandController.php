<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\BulkActionRequest;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandController extends Controller
{
    public function __construct(
        protected BrandService $brandService
    ) {}

    /**
     * Display a listing of brands.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Brand::class);

        $search = $request->input('search');
        $status = $request->input('status', 'active'); // active, trash

        $brands = Brand::when($status === 'trash', function ($query) {
            $query->onlyTrashed();
        })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Brands/Index', [
            'brands' => $brands,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Store a newly created brand.
     */
    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $logo = $request->file('logo');
        unset($validated['logo']);

        $this->brandService->create($validated, $logo);

        return redirect()->back()->with('success', 'Brand created successfully.');
    }

    /**
     * Update the specified brand.
     */
    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validated();
        $logo = $request->file('logo');
        unset($validated['logo']);

        $this->brandService->update($brand, $validated, $logo);

        return redirect()->back()->with('success', 'Brand updated successfully.');
    }

    /**
     * Remove the specified brand.
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        try {
            $this->brandService->delete($brand);

            return redirect()->back()->with('success', 'Brand deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Restore the specified soft-deleted brand.
     */
    public function restore(int $id): RedirectResponse
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $brand);

        $this->brandService->restore($brand);

        return redirect()->back()->with('success', 'Brand restored successfully.');
    }

    /**
     * Bulk destroy brands.
     */
    public function bulkDestroy(BulkActionRequest $request): RedirectResponse
    {
        $this->authorize('bulkDelete', Brand::class);
        $validated = $request->validated();

        try {
            $this->brandService->bulkDelete($validated['ids']);

            return redirect()->back()->with('success', 'Selected brands deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore soft-deleted brands.
     */
    public function bulkRestore(BulkActionRequest $request): RedirectResponse
    {
        $this->authorize('bulkRestore', Brand::class);
        $validated = $request->validated();

        $this->brandService->bulkRestore($validated['ids']);

        return redirect()->back()->with('success', 'Selected brands restored successfully.');
    }

    /**
     * Export brands to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Brand::class);

        $status = $request->input('status', 'active');
        $search = $request->input('search');

        $brands = Brand::when($status === 'trash', function ($query) {
            $query->onlyTrashed();
        })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->get();

        $headers = ['ID', 'UUID', 'Brand Name', 'Status', 'Description', 'Created At'];

        $rows = $brands->map(function ($b) {
            return [
                $b->id,
                $b->uuid,
                $b->name,
                $b->status,
                $b->description ?? 'N/A',
                $b->created_at ? $b->created_at->toIso8601String() : 'N/A',
            ];
        });

        return CsvExporter::stream('brands_export.csv', $headers, $rows);
    }
}
