<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Requests\BulkDestroyBrandRequest;
use App\Http\Requests\BulkRestoreBrandRequest;
use App\Http\Requests\BulkForceDeleteBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use App\Helpers\CsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = $request->input('sort_dir', 'desc');

        // Whitelist sorting parameters
        $allowedSorts = ['id', 'name', 'status', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $query = Brand::query();

        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->when($status !== 'all' && in_array($status, ['active', 'inactive'], true), function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        $brands = $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        })
            ->orderBy($sortBy, $sortDir)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Brands/Index', [
            'brands' => $brands,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    /**
     * Securely stream/serve a brand logo from the private local disk.
     */
    public function logo(Brand $brand): BinaryFileResponse
    {
        $this->authorize('view', $brand);

        if (!$brand->logo || !Storage::disk('local')->exists($brand->logo)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($brand->logo));
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

            return redirect()->back()->with('success', 'Brand soft-deleted successfully.');
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
     * Permanently delete a brand.
     */
    public function forceDelete(int $id): RedirectResponse
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $brand);

        try {
            $this->brandService->forceDelete($brand);
            return redirect()->back()->with('success', 'Brand permanently deleted.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk soft delete brands.
     */
    public function bulkDestroy(BulkDestroyBrandRequest $request): RedirectResponse
    {
        try {
            $this->brandService->bulkDelete($request->input('ids'));
            return redirect()->back()->with('success', 'Selected brands soft-deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore brands.
     */
    public function bulkRestore(BulkRestoreBrandRequest $request): RedirectResponse
    {
        $this->brandService->bulkRestore($request->input('ids'));

        return redirect()->back()->with('success', 'Selected brands restored successfully.');
    }

    /**
     * Bulk permanently delete brands.
     */
    public function bulkForceDelete(BulkForceDeleteBrandRequest $request): RedirectResponse
    {
        try {
            $this->brandService->bulkForceDelete($request->input('ids'));
            return redirect()->back()->with('success', 'Selected brands permanently deleted.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
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
            'Created At'
        ];

        $query = Brand::query()->orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'brands_export_' . now()->format('Y_m_d_His') . '.csv',
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
