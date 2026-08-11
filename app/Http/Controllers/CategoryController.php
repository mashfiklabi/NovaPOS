<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\BulkActionRequest;
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

        $categories = Category::with('parent')
            ->when($status === 'trash', function ($query) {
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
     * Restore the specified soft-deleted category.
     */
    public function restore(int $id): RedirectResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $category);

        $this->categoryService->restore($category);

        return redirect()->back()->with('success', 'Category restored successfully.');
    }

    /**
     * Bulk destroy categories.
     */
    public function bulkDestroy(BulkActionRequest $request): RedirectResponse
    {
        $this->authorize('bulkDelete', Category::class);
        $validated = $request->validated();

        try {
            $this->categoryService->bulkDelete($validated['ids']);

            return redirect()->back()->with('success', 'Selected categories deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore soft-deleted categories.
     */
    public function bulkRestore(BulkActionRequest $request): RedirectResponse
    {
        $this->authorize('bulkRestore', Category::class);
        $validated = $request->validated();

        $this->categoryService->bulkRestore($validated['ids']);

        return redirect()->back()->with('success', 'Selected categories restored successfully.');
    }

    /**
     * Export categories to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Category::class);

        $status = $request->input('status', 'active');
        $search = $request->input('search');

        $categories = Category::with('parent')
            ->when($status === 'trash', function ($query) {
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

        $headers = ['ID', 'UUID', 'Category Name', 'Parent Category', 'Status', 'Description', 'Created At'];

        $rows = $categories->map(function ($c) {
            return [
                $c->id,
                $c->uuid,
                $c->name,
                $c->parent ? $c->parent->name : 'N/A',
                $c->status,
                $c->description ?? 'N/A',
                $c->created_at ? $c->created_at->toIso8601String() : 'N/A',
            ];
        });

        return CsvExporter::stream('categories_export.csv', $headers, $rows);
    }
}
