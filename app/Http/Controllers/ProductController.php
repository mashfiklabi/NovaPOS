<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\BulkActionRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $products = Product::with(['category', 'brand', 'unit'])
            ->when($status === 'trash', function ($query) {
                $query->onlyTrashed();
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
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
            ],
        ]);
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

        return redirect()->back()->with('success', 'Product deleted successfully.');
    }

    /**
     * Restore the specified soft-deleted product.
     */
    public function restore(int $id): RedirectResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $product);

        $this->productService->restore($product);

        return redirect()->back()->with('success', 'Product restored successfully.');
    }

    /**
     * Bulk destroy products.
     */
    public function bulkDestroy(BulkActionRequest $request): RedirectResponse
    {
        $this->authorize('bulkDelete', Product::class);
        $validated = $request->validated();

        $this->productService->bulkDelete($validated['ids']);

        return redirect()->back()->with('success', 'Selected products deleted successfully.');
    }

    /**
     * Bulk restore soft-deleted products.
     */
    public function bulkRestore(BulkActionRequest $request): RedirectResponse
    {
        $this->authorize('bulkRestore', Product::class);
        $validated = $request->validated();

        $this->productService->bulkRestore($validated['ids']);

        return redirect()->back()->with('success', 'Selected products restored successfully.');
    }

    /**
     * Export products to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Product::class);

        $status = $request->input('status', 'active');
        $search = $request->input('search');

        $products = Product::with(['category', 'brand', 'unit'])
            ->when($status === 'trash', function ($query) {
                $query->onlyTrashed();
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->get();

        $headers = [
            'ID', 'UUID', 'Product Name', 'SKU', 'Barcode', 'Category',
            'Brand', 'Unit', 'Cost Price', 'Selling Price', 'Current Stock',
            'Alert Threshold', 'Track Stock', 'Allow Fractional', 'Tax Type',
            'Tax Rate (%)', 'Status', 'Created At',
        ];

        $rows = $products->map(function ($p) {
            return [
                $p->id,
                $p->uuid,
                $p->name,
                $p->sku,
                $p->barcode ?? 'N/A',
                $p->category ? $p->category->name : 'N/A',
                $p->brand ? $p->brand->name : 'N/A',
                $p->unit->name,
                $p->cost_price,
                $p->selling_price,
                $p->current_stock,
                $p->stock_alert_threshold,
                $p->track_stock ? 'Yes' : 'No',
                $p->allow_decimal ? 'Yes' : 'No',
                $p->tax_type,
                $p->tax_rate ?? '0.00',
                $p->status,
                $p->created_at ? $p->created_at->toIso8601String() : 'N/A',
            ];
        });

        return CsvExporter::stream('products_export.csv', $headers, $rows);
    }
}
