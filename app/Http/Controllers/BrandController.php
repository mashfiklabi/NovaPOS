<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $brands = Brand::when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Brands/Index', [
            'brands' => $brands,
            'filters' => [
                'search' => $search,
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
}
