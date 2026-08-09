<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which warehouse a goods receipt was taken into.
 *
 * The receipt endpoint already validated `warehouse_id` and used it to move the
 * stock, but the column did not exist — so `create()` dropped it and the receipt
 * itself never recorded where the goods went. That left the document unable to
 * answer the one question needed to undo it: which warehouse to take the stock
 * back out of. Editing or deleting a receipt therefore could not reverse its own
 * effect on inventory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_receipts', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('supplier_id')
                    ->constrained('warehouses')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_receipts', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });
    }
};
