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
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'sales_order_id')) {
                $table->foreignId('sales_order_id')->nullable()->after('invoice_id')->constrained('sales_orders')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'sales_order_id')) {
                $table->dropForeign(['sales_order_id']);
                $table->dropColumn('sales_order_id');
            }
        });
    }
};
