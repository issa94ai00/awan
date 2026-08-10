<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIFO cost layers.
 *
 * Cost of goods sold was taken from `products.cost_price` — one number per
 * product, whatever it was worth today. So a batch bought at 20 and a batch
 * bought at 30 both left the warehouse at whichever figure happened to be on
 * the product record, and the gross margin was wrong by the difference on
 * every unit. `warehouse_inventory.cost_basis` already claimed "FIFO" but
 * nothing implemented it: there were no layers to consume.
 *
 * Each receipt opens a layer holding the quantity and what was actually paid
 * for it. Each issue consumes layers oldest first and reports back the real
 * cost of the units it took.
 *
 * Existing stock is opened as a single layer per row at the product's current
 * cost price. That is the only defensible figure available — the history that
 * would give a true layering was never recorded — and it keeps every balance
 * exactly where it is today rather than restating the past.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();

            // What arrived, and what is left of it. `remaining_quantity` hits
            // zero when the layer is used up; the row is kept so the trail of
            // what each sale actually cost stays readable.
            $table->integer('received_quantity');
            $table->integer('remaining_quantity');
            $table->decimal('unit_cost', 12, 4);

            // What put the stock here — a purchase receipt, a transfer, or the
            // opening balance this migration writes.
            $table->string('source', 40)->nullable();
            $table->string('reference', 191)->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();

            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            // The consuming query: oldest open layer for a product in a
            // warehouse. `id` breaks ties so two layers received in the same
            // second are still consumed in arrival order.
            $table->index(['product_id', 'warehouse_id', 'remaining_quantity', 'received_at', 'id'], 'cost_layers_fifo_idx');
        });

        // Opening balances for stock that is already on the shelf.
        $now = now();
        $rows = DB::table('warehouse_inventory')
            ->join('products', 'products.id', '=', 'warehouse_inventory.product_id')
            ->where('warehouse_inventory.quantity', '>', 0)
            ->select(
                'warehouse_inventory.product_id',
                'warehouse_inventory.warehouse_id',
                'warehouse_inventory.quantity',
                'products.cost_price'
            )
            ->get();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('inventory_cost_layers')->insert($chunk->map(fn ($row) => [
                'product_id' => $row->product_id,
                'warehouse_id' => $row->warehouse_id,
                'received_quantity' => (int) $row->quantity,
                'remaining_quantity' => (int) $row->quantity,
                'unit_cost' => (float) ($row->cost_price ?? 0),
                'source' => 'opening_balance',
                'reference' => 'رصيد افتتاحي',
                'received_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
    }
};
