<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rate a discount and a tax were actually written at.
 *
 * `invoices.discount` and `invoices.tax` hold money, which is what the ledger
 * posts and what every report already reads — that does not change. What was
 * missing is the rate the seller typed: an invoice discounted "10%" and one
 * discounted "80.00" were indistinguishable once saved, so reopening the first
 * one showed a flat figure that no longer tracked the lines it came from.
 *
 * Null is meaningful here and is the default: it says this invoice was written
 * in amounts, not at a rate — every invoice raised before this migration, and
 * anything still posting plain figures to the API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'tax_percent')) {
                $table->decimal('tax_percent', 5, 2)->nullable()->after('tax');
            }

            if (! Schema::hasColumn('invoices', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->nullable()->after('discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['tax_percent', 'discount_percent'],
                fn (string $column) => Schema::hasColumn('invoices', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
