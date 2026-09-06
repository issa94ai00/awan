<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each order line cost, kept on the line — the sales-order counterpart of
 * the same columns on invoice_items.
 *
 * Order profitability was costed by multiplying each line's quantity against
 * the product's current cost_price, which is a valuation rather than a cost:
 * two orders filled from batches bought at different prices reported the same
 * margin, and re-pricing a product rewrote the reported margin of every order
 * it had ever appeared in.
 *
 * An order differs from an invoice in one way that matters here: it is a
 * commitment, and nothing has cost anything until it ships. So a null here is
 * the ordinary state of a pending order, not a fault — the report reads it as
 * "projected at catalogue cost" and says so, rather than presenting a forecast
 * as a measurement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 5)->nullable()->after('total');
            $table->decimal('total_cost', 14, 5)->nullable()->after('unit_cost');
        });

        $this->backfillFromStockMovements();
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });
    }

    /**
     * Recovers the cost of lines shipped before the columns existed.
     *
     * A shipment keys its movements `SO-{order}-{product}-W{warehouse}`, one
     * per source a line was filled from, so a line's cost is the sum of its
     * own sources. The key names the product rather than the line, so two
     * lines of one product on the same order share a set of movements; the
     * cost is split between them in proportion to the quantity each ordered,
     * which is the most those keys can tell us.
     *
     * Lines never shipped are left null, which is what the report expects of
     * an order still in the pipeline.
     */
    private function backfillFromStockMovements(): void
    {
        DB::table('sales_order_items')
            ->select('id', 'sales_order_id', 'product_id', 'quantity')
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->chunk(500, function ($lines) {
                // Quantity ordered per (order, product), so a shared set of
                // movements can be divided between the lines that share it.
                $orderedByProduct = [];
                foreach ($lines as $line) {
                    $key = $line->sales_order_id.':'.$line->product_id;
                    $orderedByProduct[$key] = ($orderedByProduct[$key] ?? 0) + (int) $line->quantity;
                }

                foreach ($lines as $line) {
                    $prefix = 'SO-'.$line->sales_order_id.'-'.$line->product_id.'-W';

                    $shipped = DB::table('stock_movements')
                        ->where('movement_key', 'like', $prefix.'%')
                        ->whereNotNull('total_cost')
                        ->sum('total_cost');

                    if ($shipped === null || (float) $shipped == 0.0) {
                        continue;
                    }

                    $quantity = (int) $line->quantity;
                    $ordered = $orderedByProduct[$line->sales_order_id.':'.$line->product_id] ?? $quantity;
                    $share = $ordered > 0 ? $quantity / $ordered : 1;
                    $cost = round((float) $shipped * $share, 5);

                    DB::table('sales_order_items')->where('id', $line->id)->update([
                        'total_cost' => $cost,
                        'unit_cost' => $quantity > 0 ? round($cost / $quantity, 5) : 0,
                    ]);
                }
            });
    }
};
