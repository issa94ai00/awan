<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sale a purchase order buys in for: a sales order or a sales invoice.
 *
 * Until now the only trace was a sentence in the notes, so nobody could get
 * from a purchase order to the customer waiting on it, or from a sale to what
 * was ordered for it. Either may be set, not both; deleting the sale leaves
 * the purchase order, unlinked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->after('supplier_id')->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->after('sales_order_id')->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropConstrainedForeignId('sales_order_id');
        });
    }
};
