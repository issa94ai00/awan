<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Defaults used to prefill a new monthly commission record —
            // each record still keeps its own snapshot so changing an
            // employee's rate later does not rewrite past months.
            $table->decimal('commission_rate', 5, 2)->nullable()->after('bonus');
            $table->decimal('monthly_sales_target', 12, 2)->nullable()->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'monthly_sales_target']);
        });
    }
};
