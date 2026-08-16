<?php

use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use Illuminate\Support\Facades\Schema;

/**
 * Guards the columns `warehouse_bins` is queried by.
 *
 * The table shipped with six columns and was never extended, while the model
 * and `WmsController` were written against nineteen. Production answered every
 * bin request with:
 *
 *   SQLSTATE[42S22]: Unknown column 'is_active' in 'where clause'
 *
 * The reason no test caught it is worth stating, because it applies to any
 * column this suite touches: these run on SQLite, which reads an unresolvable
 * double-quoted identifier as a **string literal** instead of failing. So
 * `where "is_active" = 1` silently became `where 'is_active' = 1`, matched
 * nothing, and returned an empty set with a 200. MySQL raises 1054 instead.
 *
 * Asserting the columns exist works on either engine, which is the point:
 * it catches the drift rather than the symptom.
 */

/** Every column the model's scopes, accessors and `$fillable` depend on. */
dataset('required bin columns', [
    'code', 'name', 'zone', 'aisle', 'shelf', 'level', 'type',
    'capacity_type', 'capacity_value', 'current_utilization',
    'is_active', 'requires_equipment', 'dimensions', 'coordinates', 'notes',
]);

it('has every column the bin model and controller reference', function (string $column) {
    expect(Schema::hasColumn('warehouse_bins', $column))->toBeTrue();
})->with('required bin columns');

it('keeps the original bin_code column that other queries still read', function () {
    expect(Schema::hasColumn('warehouse_bins', 'bin_code'))->toBeTrue();
});

/** Built directly: there is no Warehouse factory in this project. */
function makeWarehouse(string $code): int
{
    return DB::table('warehouses')->insertGetId([
        'name' => 'Main',
        'code' => $code,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('filters active bins without touching a missing column', function () {
    $warehouseId = makeWarehouse('MAIN');

    WarehouseBin::create([
        'warehouse_id' => $warehouseId,
        'code' => 'A-01-01',
        'bin_code' => 'A-01-01',
        'name' => 'Aisle A Bin 1',
        'type' => 'storage',
        'capacity_type' => 'count',
        'capacity_value' => 100,
        'current_utilization' => 40,
        'is_active' => true,
    ]);

    WarehouseBin::create([
        'warehouse_id' => $warehouseId,
        'code' => 'A-01-02',
        'bin_code' => 'A-01-02',
        'name' => 'Aisle A Bin 2',
        'type' => 'storage',
        'capacity_type' => 'count',
        'capacity_value' => 100,
        'current_utilization' => 0,
        'is_active' => false,
    ]);

    expect(WarehouseBin::where('warehouse_id', $warehouseId)->active()->count())->toBe(1);
});

it('reports bin utilisation from real bins', function () {
    $user = User::factory()->create();

    $warehouseId = makeWarehouse('MAIN2');

    WarehouseBin::create([
        'warehouse_id' => $warehouseId,
        'code' => 'B-01-01',
        'bin_code' => 'B-01-01',
        'name' => 'Bin',
        'type' => 'storage',
        'capacity_type' => 'count',
        'capacity_value' => 200,
        'current_utilization' => 50,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->getJson("/api/v1/analytics/warehouse/bin-utilization?warehouse_id={$warehouseId}")
        ->assertOk()
        ->assertJsonPath('total_bins', 1)
        // 50 of 200 — proof the capacity columns are readable, not just present.
        // Loose on type: JSON returns a whole number as an int.
        ->assertJsonPath('avg_utilization', fn ($value) => (float) $value === 25.0);
});
