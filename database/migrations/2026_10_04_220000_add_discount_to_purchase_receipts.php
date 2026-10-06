<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds discount and discount_percent to purchase_receipts.
 *
 * `purchase_receipts.discount` holds the monetary discount value subtracted
 * from the goods total before tax. `discount_percent` preserves the rate
 * entered or inherited from the purchase order (e.g. 10.00 for 10%).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_receipts', 'discount')) {
                $table->decimal('discount', 18, 5)->default(0)->after('tax_amount');
            }
            if (! Schema::hasColumn('purchase_receipts', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->nullable()->after('discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['discount', 'discount_percent'],
                fn (string $column) => Schema::hasColumn('purchase_receipts', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
