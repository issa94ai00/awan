<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order-item warehouse allocations.
 *
 * A single sales-order line may be fulfilled from several warehouses (the
 * quantity is split), so the plan lives in its own table instead of a
 * `warehouse_id` column on `sales_order_items`. The unique pair keeps the
 * plan deterministic: one allocation per (item, warehouse).
 *
 * The unique index carries an explicit name. Laravel would derive
 * `sales_order_item_allocations_sales_order_item_id_warehouse_id_unique` from
 * the table and column names — 68 characters, past MySQL's 64-character limit
 * for identifiers, so the ALTER failed and took the migration with it.
 *
 * `Schema::create` compiles to a CREATE TABLE followed by separate ALTER
 * statements for the keys, and MySQL does not roll DDL back. A failure at the
 * index step therefore leaves the table behind while the migration stays
 * unrecorded, so re-running would hit "table already exists". Each step is
 * guarded to make the migration safe to re-run over that wreckage.
 */
return new class extends Migration
{
    private const TABLE = 'sales_order_item_allocations';
    private const UNIQUE_INDEX = 'soi_allocations_item_warehouse_unique';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_order_item_id')
                    ->constrained('sales_order_items')
                    ->onDelete('cascade');
                $table->foreignId('warehouse_id')
                    ->constrained('warehouses')
                    ->onDelete('restrict');
                $table->integer('quantity')->default(0);
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

        // Added separately so a table left over from the failed run still gets
        // its index rather than being skipped entirely.
        if (!$this->hasIndex(self::UNIQUE_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(['sales_order_item_id', 'warehouse_id'], self::UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    /** Whether the table already carries an index of this name. */
    private function hasIndex(string $index): bool
    {
        if (!Schema::hasTable(self::TABLE)) {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', self::TABLE)
            ->where('index_name', $index)
            ->exists();
    }
};
