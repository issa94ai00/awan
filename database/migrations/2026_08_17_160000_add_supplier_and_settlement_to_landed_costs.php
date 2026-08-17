<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records who a landed cost was owed to, and how it was settled.
 *
 * The allocation raises the supplier's balance when the charge is on account,
 * but the document itself kept no trace of which supplier or how it was paid.
 * The balance moved and nothing on the record explained why — so a statement of
 * account for that carrier would show a debt with no document behind it, which
 * is exactly the kind of unexplained figure the rest of this work exists to
 * eliminate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landed_costs', function (Blueprint $table) {
            if (! Schema::hasColumn('landed_costs', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('purchase_receipt_id')
                    ->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('landed_costs', 'settlement')) {
                $table->string('settlement', 20)->default('credit')->after('allocation_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('landed_costs', function (Blueprint $table) {
            if (Schema::hasColumn('landed_costs', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }

            if (Schema::hasColumn('landed_costs', 'settlement')) {
                $table->dropColumn('settlement');
            }
        });
    }
};
