<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The percentage rate a purchase order discount was struck at.
 *
 * `purchase_orders.discount` holds the money amount, which the ledger and
 * receipts read. `discount_percent` keeps the percentage entered by the buyer
 * (e.g. 10.00 for 10%), so reopening or duplicating the order preserves
 * the agreed discount rate. Null means the order carried an amount only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->nullable()->after('discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }
        });
    }
};
