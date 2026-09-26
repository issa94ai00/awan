<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Size, colour and material only say which variant a line is. What it is —
 * "Power: 750W", "Disc: 115mm" — differs per variant too, so each one keeps
 * its own ordered list of label/value details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('specs')->nullable()->after('material');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('specs');
        });
    }
};
