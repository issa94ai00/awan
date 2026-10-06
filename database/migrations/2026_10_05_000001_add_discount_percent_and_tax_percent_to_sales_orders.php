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
        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_orders', 'tax_percent')) {
                $table->decimal('tax_percent', 5, 2)->nullable()->after('tax');
            }
            if (! Schema::hasColumn('sales_orders', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->nullable()->after('discount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['tax_percent', 'discount_percent'],
                fn (string $column) => Schema::hasColumn('sales_orders', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
