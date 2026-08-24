<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the payer actually handed over, when it was not the base currency.
 *
 * `amount` (and everything it drives — the invoice's paid/due, the customer's
 * balance, the ledger line) stays in the base currency; that boundary is why
 * a rate that moves between two payments never leaves the books unsettleable.
 * A cashier who was handed Syrian pounds still needs a receipt that says so,
 * which is all this column is for — display and audit, never accounting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'tendered_amount')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->decimal('tendered_amount', 14, 2)->nullable()->after('exchange_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'tendered_amount')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('tendered_amount');
            });
        }
    }
};
