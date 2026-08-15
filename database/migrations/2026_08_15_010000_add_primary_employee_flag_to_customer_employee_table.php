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
        Schema::table('customer_employee', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_employee', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('employee_id');
            }
        });

        Schema::table('customer_employee', function (Blueprint $table) {
            $table->index(['customer_id', 'is_primary'], 'customer_employee_customer_primary_index');
            $table->index(['employee_id', 'is_primary'], 'customer_employee_employee_primary_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_employee', function (Blueprint $table) {
            $table->dropIndex('customer_employee_customer_primary_index');
            $table->dropIndex('customer_employee_employee_primary_index');

            if (Schema::hasColumn('customer_employee', 'is_primary')) {
                $table->dropColumn('is_primary');
            }
        });
    }
};
