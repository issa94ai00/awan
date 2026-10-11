<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase return line can be for one size of a product, as the receipt
 * line it reverses is. Stock stays counted per product; the line records
 * which size went back so the size's own count can follow the goods out, as
 * receiving brought it in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('purchase_return_items', 'product_variant_id')) {
            return;
        }

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('purchase_return_items', 'product_variant_id')) {
            return;
        }

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }
};
