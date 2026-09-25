<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sales order, purchase request or goods receipt line can be for one variant
 * of a product — the 4" floor drain, not "floor drain". Stock stays counted per
 * product; the line records which variant so its name, code and price carry
 * through, and so the variant's own stock count can follow the goods.
 *
 * The invoice a sales order raises carries the variant on to its lines.
 */
return new class extends Migration
{
    private const TABLES = [
        'sales_order_items',
        'purchase_order_items',
        'purchase_receipt_items',
        'invoice_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'product_variant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('product_variant_id')->nullable()->after('product_id')
                    ->constrained('product_variants')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'product_variant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('product_variant_id');
            });
        }
    }
};
