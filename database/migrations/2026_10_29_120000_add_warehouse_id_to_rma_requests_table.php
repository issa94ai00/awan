<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The warehouse a return's goods go back into, chosen when the return is
 * raised. Receiving used to guess it (the invoice's warehouse, else whichever
 * warehouse came first). Nullable so returns filed before this keep working:
 * receipt still falls back to the old guess for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rma_requests', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('invoice_id')
                ->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rma_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
