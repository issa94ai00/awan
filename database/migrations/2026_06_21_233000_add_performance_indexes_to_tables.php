<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add composite index to contacts table
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['contactable_type', 'contactable_id']);
        });

        // 2. Add index to employees table for national_id
        Schema::table('employees', function (Blueprint $table) {
            $table->index('national_id');
        });

        // 3. Add index and foreign key to stock_movements table for warehouse_id
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasTable('warehouses')) {
                $table->foreign('warehouse_id')
                      ->references('id')
                      ->on('warehouses')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['warehouse_id']);
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['national_id']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['contactable_type', 'contactable_id']);
        });
    }
};
