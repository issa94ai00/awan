<?php

use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderCostSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each ordered line actually cost to land, kept on the line — the
 * purchasing counterpart of the cost columns on invoice and order items.
 *
 * Purchase reporting costed an order by ordered quantity against ordered
 * price, which is what was promised rather than what was paid. What actually
 * arrived is on the receipt, and what it landed at is on the stock layers the
 * receipt opened — raised afterwards by any freight, customs or insurance
 * allocated to it. None of that reached the report.
 *
 * Nullable on purpose: null means nothing has been received against the line,
 * which the report reads as "still at the ordered price" and says so, rather
 * than presenting a commitment as a settled cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->integer('received_quantity')->nullable()->after('quantity');
            $table->decimal('received_unit_cost', 14, 5)->nullable()->after('unit_price');
            $table->decimal('received_cost', 14, 5)->nullable()->after('received_unit_cost');
        });

        // Every order that has ever been received against, recomputed by the
        // same code that will keep it up to date from here on — so the history
        // and everything after it are costed identically.
        $sync = new PurchaseOrderCostSync();

        PurchaseOrder::with('items')
            ->whereHas('receipts')
            ->chunkById(100, fn ($orders) => $orders->each(fn ($order) => $sync->sync($order)));
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['received_quantity', 'received_unit_cost', 'received_cost']);
        });
    }
};
