<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $query = Unit::query();

        if ($status === 'trash') {
            $query->onlyTrashed();
        }

        $units = $query->when($search, function ($q, $search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('short_name', 'like', "%{$search}%");
        })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Units/Index', [
            'units' => $units,
            'filters' => [
                'search' => $search,
                'status' => $status,
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

            return redirect()->back()->with('success', 'Unit deleted successfully.');
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
     * Bulk soft delete units.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('bulkDelete', Unit::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:units,id',
        ]);

        try {
            $this->unitService->bulkDelete($request->input('ids'));

            return redirect()->back()->with('success', 'Selected units deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore units.
     */
    public function bulkRestore(Request $request): RedirectResponse
    {
        $this->authorize('bulkRestore', Unit::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $this->unitService->bulkRestore($request->input('ids'));

        return redirect()->back()->with('success', 'Selected units restored successfully.');
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
            'Created At',
        ];

        $query = Unit::query()->orderBy('id', 'asc');

        return CsvExporter::streamDownload(
            'units_export_'.now()->format('Y_m_d_His').'.csv',
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
