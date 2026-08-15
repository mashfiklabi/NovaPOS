<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
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

        $query = Brand::query();

        if ($status === 'trash') {
            $query->onlyTrashed();
        }

        $brands = $query->when($search, function ($q, $search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
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
     * Restore the specified soft deleted brand.
     */
    public function restore(int $id): RedirectResponse
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $brand);

        $this->brandService->restore($brand);

        return redirect()->back()->with('success', 'Brand restored successfully.');
    }

    /**
     * Bulk soft delete brands.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('bulkDelete', Brand::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:brands,id',
        ]);

        try {
            $this->brandService->bulkDelete($request->input('ids'));

            return redirect()->back()->with('success', 'Selected brands deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore brands.
     */
    public function bulkRestore(Request $request): RedirectResponse
    {
        $this->authorize('bulkRestore', Brand::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $this->brandService->bulkRestore($request->input('ids'));

        return redirect()->back()->with('success', 'Selected brands restored successfully.');
    }

    /**
     * Export brands as CSV.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('export', Brand::class);

        $headers = [
            'ID',
            'Brand Name',
            'Slug',
            'Description',
            'Status',
            'Created At',
        ];

        $query = Brand::query()->orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'brands_export_'.now()->format('Y_m_d_His').'.csv',
            $headers,
            $query,
            function (Brand $brand) {
                return [
                    $brand->id,
                    $brand->name,
                    $brand->slug,
                    $brand->description ?? '',
                    $brand->status,
                    $brand->created_at ? $brand->created_at->toIso8601String() : '',
                ];
            }
        );
    }
}
