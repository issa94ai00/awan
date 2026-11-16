<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Barcodes are unique per table at the database level; uniqueness across
 * products, variants and units is enforced by HasUniqueBarcode. Blank codes
 * become NULL first so they do not collide with each other.
 */
return new class extends Migration
{
    private const TABLES = ['products', 'product_variants', 'product_units'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->whereRaw("TRIM(barcode) = ''")->update(['barcode' => null]);

            if (! Schema::hasIndex($table, "{$table}_barcode_unique")) {
                Schema::table($table, fn (Blueprint $t) => $t->unique('barcode', "{$table}_barcode_unique"));
            }
        }

        // The plain index is now covered by the unique one.
        if (Schema::hasIndex('product_units', 'product_units_barcode_index')) {
            Schema::table('product_units', fn (Blueprint $t) => $t->dropIndex('product_units_barcode_index'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('product_units', 'product_units_barcode_index')) {
            Schema::table('product_units', fn (Blueprint $t) => $t->index('barcode', 'product_units_barcode_index'));
        }

        foreach (self::TABLES as $table) {
            if (Schema::hasIndex($table, "{$table}_barcode_unique")) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique("{$table}_barcode_unique"));
            }
        }
    }
};
