<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * Display a listing of categories.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $search = $request->input('search');

        $categories = Category::with('parent')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Fetch parent candidates for subcategories
        $parentCategories = Category::whereNull('parent_id')
            ->select('id', 'name')
            ->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'parentCategories' => $parentCategories,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->categoryService->create($validated);

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $validated = $request->validated();

        $this->categoryService->update($category, $validated);

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        try {
            $this->categoryService->delete($category);

            return redirect()->back()->with('success', 'Category deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
