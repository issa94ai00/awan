<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * unit_price on a receipt line is the cost actually paid. sale_price is the
 * shelf price the operator wants the product to carry from this purchase
 * onward — captured here so receiving a purchase can push it onto the
 * product, the same moment its cost is rolled into the average.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->decimal('sale_price', 15, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->dropColumn('sale_price');
        });
    }
};
