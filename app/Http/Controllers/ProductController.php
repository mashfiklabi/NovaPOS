<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\BulkDestroyProductRequest;
use App\Http\Requests\BulkRestoreProductRequest;
use App\Http\Requests\BulkForceDeleteProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\ProductService;
use App\Helpers\CsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = $request->input('search');
        $status = $request->input('status', 'active'); // active, trash
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = $request->input('sort_dir', 'desc');

        // Whitelist sorting parameters
        $allowedSorts = ['id', 'name', 'sku', 'cost_price', 'selling_price', 'current_stock', 'status', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $query = Product::with(['category', 'brand', 'unit']);

        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->when($status !== 'all' && in_array($status, ['active', 'inactive', 'out_of_stock', 'discontinued'], true), function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        $products = $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                   ->orWhere('sku', 'like', "%{$search}%")
                   ->orWhere('barcode', 'like', "%{$search}%");
            });
        })
            ->orderBy($sortBy, $sortDir)
            ->paginate(10)
            ->withQueryString();

        // Fetch selection choices
        $categories = Category::where('status', 'active')->select('id', 'name')->get();
        $brands = Brand::where('status', 'active')->select('id', 'name')->get();
        $units = Unit::select('id', 'name', 'short_name')->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'units' => $units,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    /**
     * Securely stream/serve a product image from the private local disk.
     */
    public function image(Product $product): BinaryFileResponse
    {
        $this->authorize('view', $product);

        if (!$product->image || !Storage::disk('local')->exists($product->image)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($product->image));
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $image = $request->file('image');
        unset($validated['image']);

        $this->productService->create($validated, $image);

        return redirect()->back()->with('success', 'Product created successfully.');
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $image = $request->file('image');
        unset($validated['image']);

        $this->productService->update($product, $validated, $image);

        return redirect()->back()->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        return redirect()->back()->with('success', 'Product soft-deleted successfully.');
    }

    /**
     * Restore the specified soft deleted product.
     */
    public function restore(int $id): RedirectResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $product);

        $this->productService->restore($product);

        return redirect()->back()->with('success', 'Product restored successfully.');
    }

    /**
     * Permanently delete a product.
     */
    public function forceDelete(int $id): RedirectResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $product);

        $this->productService->forceDelete($product);

        return redirect()->back()->with('success', 'Product permanently deleted.');
    }

    /**
     * Bulk soft delete products.
     */
    public function bulkDestroy(BulkDestroyProductRequest $request): RedirectResponse
    {
        try {
            $this->productService->bulkDelete($request->input('ids'));
            return redirect()->back()->with('success', 'Selected products soft-deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore products.
     */
    public function bulkRestore(BulkRestoreProductRequest $request): RedirectResponse
    {
        $this->productService->bulkRestore($request->input('ids'));

        return redirect()->back()->with('success', 'Selected products restored successfully.');
    }

    /**
     * Bulk permanently delete products.
     */
    public function bulkForceDelete(BulkForceDeleteProductRequest $request): RedirectResponse
    {
        try {
            $this->productService->bulkForceDelete($request->input('ids'));
            return redirect()->back()->with('success', 'Selected products permanently deleted.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Export products as CSV.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('export', Product::class);

        $headers = [
            'ID',
            'Product Name',
            'Slug',
            'SKU',
            'Barcode',
            'Category',
            'Brand',
            'Unit',
            'Purchase Price',
            'Selling Price',
            'Minimum Stock',
            'Current Stock',
            'Status',
            'Track Stock',
            'Allow Decimal',
            'Tax Type',
            'Tax Rate',
            'Created At'
        ];

        $query = Product::with(['category', 'brand', 'unit'])->orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'products_export_' . now()->format('Y_m_d_His') . '.csv',
            $headers,
            $query,
            function (Product $product) {
                return [
                    $product->id,
                    $product->name,
                    $product->slug,
                    $product->sku,
                    $product->barcode ?? '',
                    $product->category ? $product->category->name : '',
                    $product->brand ? $product->brand->name : '',
                    $product->unit ? $product->unit->name : '',
                    $product->cost_price,
                    $product->selling_price,
                    $product->stock_alert_threshold,
                    $product->current_stock,
                    $product->status,
                    $product->track_stock ? 'Yes' : 'No',
                    $product->allow_decimal ? 'Yes' : 'No',
                    $product->tax_type,
                    $product->tax_rate,
                    $product->created_at ? $product->created_at->toIso8601String() : '',
                ];
            }
        );
    }
}
