<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\CsvExporter;
use App\Http\Requests\BulkActionRequest;
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

        $units = Unit::when($status === 'trash', function ($query) {
            $query->onlyTrashed();
        })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('short_name', 'like', "%{$search}%");
                });
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
     * Restore the specified soft-deleted unit.
     */
    public function restore(int $id): RedirectResponse
    {
        $unit = Unit::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $unit);

        $this->unitService->restore($unit);

        return redirect()->back()->with('success', 'Unit restored successfully.');
    }

    /**
     * Bulk destroy units.
     */
    public function bulkDestroy(BulkActionRequest $request): RedirectResponse
    {
        $this->authorize('bulkDelete', Unit::class);
        $validated = $request->validated();

        try {
            $this->unitService->bulkDelete($validated['ids']);

            return redirect()->back()->with('success', 'Selected units deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk restore soft-deleted units.
     */
    public function bulkRestore(BulkActionRequest $request): RedirectResponse
    {
        $this->authorize('bulkRestore', Unit::class);
        $validated = $request->validated();

        $this->unitService->bulkRestore($validated['ids']);

        return redirect()->back()->with('success', 'Selected units restored successfully.');
    }

    /**
     * Export units to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Unit::class);

        $status = $request->input('status', 'active');
        $search = $request->input('search');

        $units = Unit::when($status === 'trash', function ($query) {
            $query->onlyTrashed();
        })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('short_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->get();

        $headers = ['ID', 'UUID', 'Unit Name', 'Short Name', 'Allow Decimals', 'Created At'];

        $rows = $units->map(function ($u) {
            return [
                $u->id,
                $u->uuid,
                $u->name,
                $u->short_name,
                $u->allow_decimal,
                $u->created_at ? $u->created_at->toIso8601String() : 'N/A',
            ];
        });

        return CsvExporter::stream('units_export.csv', $headers, $rows);
    }
}
