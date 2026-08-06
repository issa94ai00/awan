<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Connects the two inventory records that had drifted apart.
 *
 * `products.stock_quantity` held every unit the business owns, while
 * `warehouse_inventory` — the table the entire WMS reads for allocation,
 * picking, transfers and reorder points — was empty. Every availability check
 * therefore answered "none in stock" no matter how full the shelves were.
 *
 * This opens a warehouse row for each product that has stock, so the WMS starts
 * from the position the business is actually in, and adds `movement_key` so a
 * document can only move stock once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'movement_key')) {
                // e.g. "invoice:12:item:5" — unique, so replaying an event is a
                // no-op rather than a second withdrawal from the shelf.
                $table->string('movement_key', 191)->nullable()->unique()->after('source');
            }
        });

        $this->seedWarehouseInventory();
    }

    /**
     * Opens a warehouse row per product from the cached total.
     *
     * Only runs where there is nothing to lose: products that already have a
     * warehouse row are skipped entirely, so re-running cannot inflate stock,
     * and no stock_movements are written — these are opening balances, not
     * movements, and inventing history would corrupt the audit trail.
     */
    private function seedWarehouseInventory(): void
    {
        $warehouseId = DB::table('warehouses')->orderBy('id')->value('id');

        if (!$warehouseId) {
            // Nothing to seed into; the service opens rows on demand later.
            return;
        }

        $alreadyStocked = DB::table('warehouse_inventory')->distinct()->pluck('product_id')->all();

        DB::table('products')
            ->where('stock_quantity', '>', 0)
            ->when($alreadyStocked, fn ($q) => $q->whereNotIn('id', $alreadyStocked))
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($warehouseId) {
                $now = now();
                $rows = [];

                foreach ($products as $product) {
                    $quantity = (int) $product->stock_quantity;

                    $rows[] = [
                        'warehouse_id' => $warehouseId,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'available_quantity' => $quantity,
                        'damaged_quantity' => 0,
                        'quarantined_quantity' => 0,
                        'reserved_quantity' => 0,
                        'reorder_point' => 0,
                        'safety_stock' => 0,
                        'lead_time_days' => 0,
                        'average_daily_sales' => 0,
                        'auto_reorder_enabled' => 0,
                        'count_variance' => 0,
                        'cost_basis' => 'FIFO',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('warehouse_inventory')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'movement_key')) {
                $table->dropUnique(['movement_key']);
                $table->dropColumn('movement_key');
            }
        });

        // Seeded opening balances are intentionally left in place: dropping them
        // would empty the WMS again, and by now real movements may reference them.
    }
};
