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
            if (! Schema::hasColumn('invoices', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('sales_order_id')->constrained('warehouses')->nullOnDelete();
            }
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'sales_orders_customer_status_index');
            $table->index(['assigned_employee_id', 'status'], 'sales_orders_employee_status_index');
            $table->index(['fulfillment_warehouse_id', 'status'], 'sales_orders_warehouse_status_index');
            $table->index(['created_at'], 'sales_orders_created_at_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'invoices_customer_status_index');
            $table->index(['sales_order_id'], 'invoices_sales_order_index');
            $table->index(['warehouse_id', 'status'], 'invoices_warehouse_status_index');
            $table->index(['created_at'], 'invoices_created_at_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['employee_id', 'status'], 'customers_employee_status_index');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index(['warehouse_id', 'status'], 'employees_warehouse_status_index');
        });

        Schema::table('customer_employee', function (Blueprint $table) {
            $table->index(['customer_id'], 'customer_employee_customer_index');
            $table->index(['employee_id'], 'customer_employee_employee_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_employee', function (Blueprint $table) {
            $table->dropIndex('customer_employee_customer_index');
            $table->dropIndex('customer_employee_employee_index');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_warehouse_status_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_employee_status_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_customer_status_index');
            $table->dropIndex('invoices_sales_order_index');
            $table->dropIndex('invoices_warehouse_status_index');
            $table->dropIndex('invoices_created_at_index');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex('sales_orders_customer_status_index');
            $table->dropIndex('sales_orders_employee_status_index');
            $table->dropIndex('sales_orders_warehouse_status_index');
            $table->dropIndex('sales_orders_created_at_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });
    }
};
