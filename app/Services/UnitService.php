<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class UnitService
{
    /**
     * Create a new unit.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Unit
    {
        return DB::transaction(function () use ($data) {
            return Unit::create($data);
        });
    }

    /**
     * Update an existing unit.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Unit $unit, array $data): Unit
    {
        return DB::transaction(function () use ($unit, $data) {
            $unit->update($data);

            return $unit;
        });
    }

    /**
     * Soft delete a unit.
     */
    public function delete(Unit $unit): void
    {
        if ($unit->products()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete a unit associated with existing products.');
        }

        DB::transaction(function () use ($unit) {
            $unit->delete();
        });
    }
}
