<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure warehouse_inventory.cost_basis can hold any supported costing method:
        // FIFO, WEIGHTED_AVERAGE, LIFO, FEFO
        if (Schema::hasTable('warehouse_inventory')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `warehouse_inventory` MODIFY COLUMN `cost_basis` VARCHAR(32) NOT NULL DEFAULT 'FIFO'");
            } else {
                Schema::table('warehouse_inventory', function (Blueprint $table) {
                    $table->string('cost_basis', 32)->default('FIFO')->change();
                });
            }
        }

        // 2. Add cost_basis to product_warehouse_assignments if not present
        if (Schema::hasTable('product_warehouse_assignments')) {
            if (! Schema::hasColumn('product_warehouse_assignments', 'cost_basis')) {
                Schema::table('product_warehouse_assignments', function (Blueprint $table) {
                    $table->string('cost_basis', 32)->nullable()->after('putaway_strategy');
                });
            }
        }

        // 3. Ensure default inventory_cost_method setting exists
        if (Schema::hasTable('settings')) {
            Setting::firstOrCreate(
                ['key' => 'inventory_cost_method'],
                ['value' => 'FIFO']
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_warehouse_assignments')) {
            if (Schema::hasColumn('product_warehouse_assignments', 'cost_basis')) {
                Schema::table('product_warehouse_assignments', function (Blueprint $table) {
                    $table->dropColumn('cost_basis');
                });
            }
        }

        if (Schema::hasTable('warehouse_inventory')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `warehouse_inventory` MODIFY COLUMN `cost_basis` ENUM('FIFO', 'FEFO', 'LIFO') NOT NULL DEFAULT 'FIFO'");
            }
        }
    }
};
