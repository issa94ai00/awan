<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives `warehouse_bins` the columns the rest of the system already assumes.
 *
 * The table was created with six columns — warehouse_id, bin_code, zone, rack,
 * shelf, max_weight — and nothing ever added to it. Meanwhile `WarehouseBin`
 * declares thirteen more in `$fillable`, `scopeActive()` filters on `is_active`,
 * `isFull()` and `getUtilizationPercentageAttribute()` read `capacity_value` and
 * `current_utilization`, and `WmsController::storeBin()` validates every one of
 * them. So bin management and bin analytics were both querying a shape that has
 * never existed:
 *
 *   SQLSTATE[42S22]: Unknown column 'is_active' in 'where clause'
 *
 * This did not surface in the test suite because those run on SQLite, which
 * reads an unresolvable double-quoted identifier as a string literal rather
 * than raising — `"is_active" = 1` quietly becomes `'is_active' = 1`, matches
 * nothing, and returns an empty result. MySQL rejects it outright.
 *
 * Columns are added to match the model and the controller's validation exactly,
 * so nothing has to be edited on either side.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_bins')) {
            return;
        }

        Schema::table('warehouse_bins', function (Blueprint $table) {
            // `code` is what the bin screens and product assignments read;
            // `bin_code` is the original column and stays, because other code
            // paths still select it. Added nullable here and made unique below,
            // after existing rows have been given a value.
            if (! Schema::hasColumn('warehouse_bins', 'code')) {
                $table->string('code')->nullable()->after('warehouse_id');
            }

            if (! Schema::hasColumn('warehouse_bins', 'name')) {
                $table->string('name')->nullable()->after('bin_code');
            }

            if (! Schema::hasColumn('warehouse_bins', 'aisle')) {
                $table->string('aisle')->nullable()->after('zone');
            }

            if (! Schema::hasColumn('warehouse_bins', 'level')) {
                $table->string('level')->nullable()->after('shelf');
            }

            if (! Schema::hasColumn('warehouse_bins', 'type')) {
                $table->string('type')->default('storage');
            }

            if (! Schema::hasColumn('warehouse_bins', 'capacity_type')) {
                $table->string('capacity_type')->default('count');
            }

            if (! Schema::hasColumn('warehouse_bins', 'capacity_value')) {
                $table->decimal('capacity_value', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('warehouse_bins', 'current_utilization')) {
                $table->decimal('current_utilization', 12, 2)->default(0);
            }

            // Existing bins are in use, so they default to active rather than
            // disappearing from every screen the moment this runs.
            if (! Schema::hasColumn('warehouse_bins', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            if (! Schema::hasColumn('warehouse_bins', 'requires_equipment')) {
                $table->boolean('requires_equipment')->default(false);
            }

            if (! Schema::hasColumn('warehouse_bins', 'dimensions')) {
                $table->json('dimensions')->nullable();
            }

            if (! Schema::hasColumn('warehouse_bins', 'coordinates')) {
                $table->json('coordinates')->nullable();
            }

            if (! Schema::hasColumn('warehouse_bins', 'notes')) {
                $table->text('notes')->nullable();
            }
        });

        // Carry the identifier the rows already have across to the new column,
        // and give the name something readable, before anything relies on them.
        DB::table('warehouse_bins')->whereNull('code')->update([
            'code' => DB::raw('bin_code'),
        ]);

        DB::table('warehouse_bins')->whereNull('name')->update([
            'name' => DB::raw('bin_code'),
        ]);

        // `storeBin` validates `unique:warehouse_bins,code`, which needs the
        // index to exist. Safe now that every row is populated from the
        // already-unique `bin_code`.
        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->unique('code', 'warehouse_bins_code_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('warehouse_bins')) {
            return;
        }

        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->dropUnique('warehouse_bins_code_unique');
        });

        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'name', 'aisle', 'level', 'type', 'capacity_type',
                'capacity_value', 'current_utilization', 'is_active',
                'requires_equipment', 'dimensions', 'coordinates', 'notes',
            ]);
        });
    }
};
