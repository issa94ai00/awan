<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('product_unit_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('unit_name')->nullable()->after('product_unit_id');
            $table->decimal('unit_multiplier', 10, 2)->default(1)->after('unit_name');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn(['product_unit_id', 'unit_name', 'unit_multiplier']);
        });
    }
};
