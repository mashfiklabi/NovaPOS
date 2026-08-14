<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\BulkDestroyCategoryRequest;
use App\Http\Requests\BulkRestoreCategoryRequest;
use App\Http\Requests\BulkForceDeleteCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use App\Helpers\CsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        $status = $request->input('status', 'active'); // active, trash
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = $request->input('sort_dir', 'desc');

        // Whitelist sorting parameters
        $allowedSorts = ['id', 'name', 'status', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $query = Category::with('parent');

        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            // Apply standard status filters
            $query->when($status !== 'all' && in_array($status, ['active', 'inactive'], true), function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        $categories = $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        })
            ->orderBy($sortBy, $sortDir)
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
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
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

            return redirect()->back()->with('success', 'Category soft-deleted successfully.');
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
     * Permanently delete a category.
     */
    public function forceDelete(int $id): RedirectResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $category);

        try {
            $this->categoryService->forceDelete($category);
            return redirect()->back()->with('success', 'Category permanently deleted.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk soft delete categories.
     */
    public function bulkDestroy(BulkDestroyCategoryRequest $request): RedirectResponse
    {
        try {
            $this->categoryService->bulkDelete($request->input('ids'));
            return redirect()->back()->with('success', 'Selected categories soft-deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore categories.
     */
    public function bulkRestore(BulkRestoreCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->bulkRestore($request->input('ids'));

        return redirect()->back()->with('success', 'Selected categories restored successfully.');
    }

    /**
     * Bulk permanently delete categories.
     */
    public function bulkForceDelete(BulkForceDeleteCategoryRequest $request): RedirectResponse
    {
        try {
            $this->categoryService->bulkForceDelete($request->input('ids'));
            return redirect()->back()->with('success', 'Selected categories permanently deleted.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
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
            'Created At'
        ];

        $query = Category::with('parent')->orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'categories_export_' . now()->format('Y_m_d_His') . '.csv',
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
