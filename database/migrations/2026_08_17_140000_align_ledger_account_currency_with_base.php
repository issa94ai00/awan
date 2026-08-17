<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the chart of accounts agree with the currency the books are kept in.
 *
 * Every account carried the literal 'SAR', written once by the migration that
 * seeded the chart and never revisited, while the configured base currency was
 * something else entirely. Nothing computed from that label — the ledger is
 * single-currency and posts in the base regardless — but a chart that names a
 * currency the system does not use is a trap: the first person to reconcile a
 * statement against a bank in another currency has no way to tell which of the
 * two is lying.
 *
 * Only labels change here. No amount is touched and no entry is re-stated:
 * what was posted was posted in the base, and each entry now carries its own
 * `base_currency` stamp recording which base that was.
 *
 * From here the two stay together — `CurrencyService::setBase()` moves the
 * labels with the base.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ledger_accounts') || ! Schema::hasColumn('ledger_accounts', 'currency')) {
            return;
        }

        $base = $this->baseCode();

        DB::table('ledger_accounts')
            ->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', '!=', $base))
            ->update(['currency' => $base, 'updated_at' => now()]);
    }

    /**
     * Read from the table rather than through CurrencyService: a migration runs
     * against a schema that may predate the service's assumptions, and this
     * matches its own fallback.
     */
    private function baseCode(): string
    {
        if (! Schema::hasTable('currencies')) {
            return 'USD';
        }

        return (string) (DB::table('currencies')->where('is_base', 1)->value('code') ?: 'USD');
    }

    public function down(): void
    {
        // Not reversible: the previous value was a literal nobody had chosen.
    }
};
