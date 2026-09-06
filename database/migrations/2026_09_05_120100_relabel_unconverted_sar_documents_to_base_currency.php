<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relabels documents that were never in riyals to begin with.
 *
 * The companion migration removed `currency DEFAULT 'SAR'` from the schema.
 * The rows written while it stood are still carrying that label, and the
 * accounting health check reports them — correctly — as documents in a
 * currency the books are not kept in.
 *
 * They are not foreign documents. On these books:
 *
 *   - payments recorded as USD settle invoices recorded as SAR for exactly
 *     the same figure (252.85, 180.00, 32.00). A riyal payment cannot clear a
 *     dollar invoice of identical face value;
 *   - every journal entry ever posted is in the base currency;
 *   - every one of these rows carries exchange_rate 1.0;
 *   - nothing converts at posting time, so the amounts entered the ledger at
 *     face value and agree with it.
 *
 * The figures are right and only the label is wrong, so this changes the
 * label and nothing else. Not one amount is touched.
 *
 * The `exchange_rate = 1` condition is the safety catch: a document genuinely
 * billed in another currency would carry a real rate, and this migration will
 * not touch it. Tables with no rate column hold no amounts that could have
 * been converted (suppliers) or were written by the same defaulting path.
 */
return new class extends Migration
{
    private const TABLES = [
        'invoices',
        'payments',
        'purchase_orders',
        'purchase_receipts',
        'suppliers',
    ];

    public function up(): void
    {
        $base = base_currency_code();

        // On books actually kept in riyals there is nothing to correct, and
        // relabelling would be the very mistake this repairs.
        if ($base === 'SAR') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) {
                continue;
            }

            $query = DB::table($table)->where('currency', 'SAR');

            if (Schema::hasColumn($table, 'exchange_rate')) {
                // Never converted, so never really foreign.
                $query->where(fn ($q) => $q->where('exchange_rate', 1)->orWhereNull('exchange_rate'));
            }

            $query->update(['currency' => $base]);
        }
    }

    public function down(): void
    {
        // Not reversed. Which rows were relabelled is not recorded, and
        // stamping 'SAR' back onto every base-currency document would invent
        // foreign currency where there is none. The pre-change rows were
        // snapshotted to storage/backups/ before this ran.
    }
};
