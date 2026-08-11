<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        $products = Product::with(['category', 'brand', 'unit'])
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
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
}
