<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every sales report query filters and sorts sales_orders by order_date —
 * the existing composite indexes (customer_status, employee_status,
 * warehouse_status) all pair a dimension with status, none with the date
 * column every request actually touches. invoices already has this index
 * on created_at; sales_orders never got the equivalent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['order_date']);
        });
    }
};
