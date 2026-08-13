<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $status = $request->input('status', 'active'); // active, trash

        $query = Category::with('parent');

        if ($status === 'trash') {
            $query->onlyTrashed();
        }

        $categories = $query->when($search, function ($q, $search) {
            $q->where('name', 'like', "%{$search}%")
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
                'status' => $status,
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

    /**
     * Restore the specified soft deleted category.
     */
    public function restore(int $id): RedirectResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $category);

        $this->categoryService->restore($category);

        return redirect()->back()->with('success', 'Category restored successfully.');
    }

    /**
     * Bulk soft delete categories.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('bulkDelete', Category::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:categories,id',
        ]);

        try {
            $this->categoryService->bulkDelete($request->input('ids'));

            return redirect()->back()->with('success', 'Selected categories deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore categories.
     */
    public function bulkRestore(Request $request): RedirectResponse
    {
        $this->authorize('bulkRestore', Category::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $this->categoryService->bulkRestore($request->input('ids'));

        return redirect()->back()->with('success', 'Selected categories restored successfully.');
    }

    /**
     * Export categories as CSV.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('export', Category::class);

        $headers = [
            'ID',
            'Category Name',
            'Slug',
            'Description',
            'Parent Category',
            'Status',
            'Created At',
        ];

        $query = Category::with('parent')->orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'categories_export_'.now()->format('Y_m_d_His').'.csv',
            $headers,
            $query,
            function (Category $category) {
                return [
                    $category->id,
                    $category->name,
                    $category->slug,
                    $category->description ?? '',
                    $category->parent ? $category->parent->name : 'None',
                    $category->status,
                    $category->created_at ? $category->created_at->toIso8601String() : '',
                ];
            }
        );
    }
}
