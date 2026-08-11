<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $units = Unit::when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('short_name', 'like', "%{$search}%");
        })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Units/Index', [
            'units' => $units,
            'filters' => [
                'search' => $search,
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
}
