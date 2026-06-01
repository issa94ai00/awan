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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            }
            if (!Schema::hasColumn('invoices', 'sales_order_id')) {
                $table->foreignId('sales_order_id')->nullable()->constrained()->onDelete('set null');
            }
            if (!Schema::hasColumn('invoices', 'paid_amount')) {
                $table->decimal('paid_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('invoices', 'due_amount')) {
                $table->decimal('due_amount', 10, 2)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('invoices', 'sales_order_id')) {
                $table->dropForeign(['sales_order_id']);
                $table->dropColumn('sales_order_id');
            }
            if (Schema::hasColumn('invoices', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('invoices', 'due_amount')) {
                $table->dropColumn('due_amount');
            }
        });
    }
};
