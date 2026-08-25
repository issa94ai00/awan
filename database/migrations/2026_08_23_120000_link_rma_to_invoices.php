<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RMA returns were modeled entirely against sales_orders/sales_order_items,
 * but this business creates invoices directly — sales_orders has never held
 * a row. That made the "which invoice is this a return for" picker (and
 * everything downstream of it: refund settlement, credit notes) permanently
 * empty. Both new columns are additive and nullable so existing rows are
 * untouched; sales_order_id/sales_order_item_id are left in place rather
 * than dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rma_requests', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('sales_order_id')
                ->constrained('invoices')->nullOnDelete();
            $table->index('invoice_id');
        });

        Schema::table('rma_items', function (Blueprint $table) {
            $table->foreignId('invoice_item_id')->nullable()->after('sales_order_item_id')
                ->constrained('invoice_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rma_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });

        Schema::table('rma_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_item_id');
        });
    }
};
