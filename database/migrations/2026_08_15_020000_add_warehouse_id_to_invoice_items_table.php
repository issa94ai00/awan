<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('invoice_id')->constrained('warehouses')->nullOnDelete();
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->index(['invoice_id', 'warehouse_id'], 'invoice_items_invoice_warehouse_index');
            $table->index(['warehouse_id', 'product_id'], 'invoice_items_warehouse_product_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex('invoice_items_invoice_warehouse_index');
            $table->dropIndex('invoice_items_warehouse_product_index');

            if (Schema::hasColumn('invoice_items', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });
    }
};
