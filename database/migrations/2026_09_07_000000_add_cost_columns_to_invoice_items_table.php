<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each invoice line actually cost, kept on the line.
 *
 * Profit reporting costed a sale by multiplying its quantity against the
 * product's *current* cost_price. That is a valuation, not a cost: goods
 * bought in two batches at different prices were both valued at whatever the
 * catalogue said today, and editing a product's cost silently rewrote the
 * reported profit of every invoice that had ever contained it.
 *
 * The real figure already exists — the goods issue consumes FIFO layers and
 * stamps what the units cost onto the stock movement — but it lived only in
 * the warehouse ledger, where no sales report could reach it without guessing
 * at join keys. It is now written onto the line as the sale is made.
 *
 * Nullable on purpose: null means "never costed", which is not the same
 * statement as zero, and the report says so rather than quietly reading an
 * uncosted line as pure profit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 5)->nullable()->after('total_price');
            $table->decimal('total_cost', 14, 5)->nullable()->after('unit_cost');
        });

        $this->backfillFromStockMovements();
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });
    }

    /**
     * Recovers the cost of lines sold before the column existed.
     *
     * The issue stamped each movement with `invoice:{invoice}:item:{line}`, so
     * every line billed since cost posting was introduced can be matched back
     * exactly. Matched in PHP rather than by a SQL join on a concatenated key:
     * the concat operator differs between MySQL and the SQLite the tests run
     * on, and this runs once.
     *
     * Lines with no movement — invoices raised before cost posting, or edited
     * afterwards, which replaces the rows the movements were keyed to — are
     * left null and fall back to the catalogue estimate in the report, flagged
     * as an estimate rather than passed off as measured.
     */
    private function backfillFromStockMovements(): void
    {
        DB::table('invoice_items')
            ->select('id', 'invoice_id', 'quantity')
            ->orderBy('id')
            ->chunk(500, function ($lines) {
                $keys = $lines->map(fn ($line) => 'invoice:'.$line->invoice_id.':item:'.$line->id)->all();

                $costs = DB::table('stock_movements')
                    ->whereIn('movement_key', $keys)
                    ->whereNotNull('total_cost')
                    ->pluck('total_cost', 'movement_key');

                foreach ($lines as $line) {
                    $cost = $costs->get('invoice:'.$line->invoice_id.':item:'.$line->id);

                    if ($cost === null) {
                        continue;
                    }

                    $quantity = (int) $line->quantity;

                    DB::table('invoice_items')->where('id', $line->id)->update([
                        'total_cost' => round((float) $cost, 5),
                        'unit_cost' => $quantity > 0 ? round((float) $cost / $quantity, 5) : 0,
                    ]);
                }
            });
    }
};
