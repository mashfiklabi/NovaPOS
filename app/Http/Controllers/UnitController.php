<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Requests\BulkDestroyUnitRequest;
use App\Http\Requests\BulkRestoreUnitRequest;
use App\Http\Requests\BulkForceDeleteUnitRequest;
use App\Models\Unit;
use App\Services\UnitService;
use App\Helpers\CsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function __construct(
        protected UnitService $unitService
    ) {}

    /**
     * Display a listing of units.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Unit::class);

        $search = $request->input('search');
        $status = $request->input('status', 'active'); // active, trash
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = $request->input('sort_dir', 'desc');

        // Whitelist sorting parameters
        $allowedSorts = ['id', 'name', 'short_name', 'allow_decimal', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $query = Unit::query();

        if ($status === 'trash') {
            $query->onlyTrashed();
        }

        $units = $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%");
            });
        })
            ->orderBy($sortBy, $sortDir)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Units/Index', [
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
     * Store a newly created unit.
     */
    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->unitService->create($validated);

        return redirect()->back()->with('success', 'Unit created successfully.');
    }

    /**
     * Update the specified unit.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validated();

        $this->unitService->update($unit, $validated);

        return redirect()->back()->with('success', 'Unit updated successfully.');
    }

    /**
     * Remove the specified unit.
     */
    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        try {
            $this->unitService->delete($unit);

            return redirect()->back()->with('success', 'Unit soft-deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Restore the specified soft deleted unit.
     */
    public function restore(int $id): RedirectResponse
    {
        $unit = Unit::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $unit);

        $this->unitService->restore($unit);

        return redirect()->back()->with('success', 'Unit restored successfully.');
    }

    /**
     * Permanently delete a unit.
     */
    public function forceDelete(int $id): RedirectResponse
    {
        $unit = Unit::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $unit);

        try {
            $this->unitService->forceDelete($unit);
            return redirect()->back()->with('success', 'Unit permanently deleted.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk soft delete units.
     */
    public function bulkDestroy(BulkDestroyUnitRequest $request): RedirectResponse
    {
        try {
            $this->unitService->bulkDelete($request->input('ids'));
            return redirect()->back()->with('success', 'Selected units soft-deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore units.
     */
    public function bulkRestore(BulkRestoreUnitRequest $request): RedirectResponse
    {
        $this->unitService->bulkRestore($request->input('ids'));

        return redirect()->back()->with('success', 'Selected units restored successfully.');
    }

    /**
     * Bulk permanently delete units.
     */
    public function bulkForceDelete(BulkForceDeleteUnitRequest $request): RedirectResponse
    {
        try {
            $this->unitService->bulkForceDelete($request->input('ids'));
            return redirect()->back()->with('success', 'Selected units permanently deleted.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Export units as CSV.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('export', Unit::class);

        $headers = [
            'ID',
            'Unit Name',
            'Short Name',
            'Allow Decimal',
            'Created At'
        ];

        $query = Unit::query()->orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'units_export_' . now()->format('Y_m_d_His') . '.csv',
            $headers,
            $query,
            function (Unit $unit) {
                return [
                    $unit->id,
                    $unit->name,
                    $unit->short_name,
                    $unit->allow_decimal,
                    $unit->created_at ? $unit->created_at->toIso8601String() : '',
                ];
            }
        );
    }
}
