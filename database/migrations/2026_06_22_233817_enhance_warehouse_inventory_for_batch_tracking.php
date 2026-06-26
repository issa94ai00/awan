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
        Schema::table('warehouse_inventory', function (Blueprint $table) {
            $table->foreignId('bin_id')->nullable()->constrained('warehouse_bins')->after('product_variant_id');
            $table->string('batch_number')->nullable()->after('bin_id');
            $table->date('expiry_date')->nullable()->after('batch_number');
            $table->json('serial_numbers')->nullable()->after('expiry_date');
            $table->enum('cost_basis', ['FIFO', 'FEFO', 'LIFO'])->default('FIFO')->after('serial_numbers');
            $table->timestamp('last_counted_at')->nullable()->after('cost_basis');
            $table->integer('count_variance')->default(0)->after('last_counted_at');
            $table->integer('available_quantity')->default(0)->after('quantity');
            $table->integer('damaged_quantity')->default(0)->after('available_quantity');
            $table->integer('quarantined_quantity')->default(0)->after('damaged_quantity');
            $table->integer('lead_time_days')->default(7)->after('safety_stock');
            $table->decimal('average_daily_sales', 10, 2)->default(0)->after('lead_time_days');
            $table->timestamp('last_reorder_at')->nullable()->after('average_daily_sales');
            $table->boolean('auto_reorder_enabled')->default(false)->after('last_reorder_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_inventory', function (Blueprint $table) {
            $table->dropForeign(['bin_id']);
            $table->dropColumn([
                'bin_id', 'batch_number', 'expiry_date', 'serial_numbers', 'cost_basis',
                'last_counted_at', 'count_variance', 'available_quantity', 'damaged_quantity',
                'quarantined_quantity', 'lead_time_days', 'average_daily_sales', 'last_reorder_at', 'auto_reorder_enabled'
            ]);
        });
    }
};
