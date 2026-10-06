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
        Schema::table('purchase_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_receipts', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->nullable()->change();
            } else {
                $table->decimal('discount_percent', 5, 2)->nullable()->after('discount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep as is for safety
    }
};
