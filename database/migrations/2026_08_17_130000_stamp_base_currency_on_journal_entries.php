<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Says, on every entry, what currency its amounts are actually in.
 *
 * The books are kept in one currency by design — `CurrencyService` converts for
 * display only, and never on the way in. The journal, though, recorded a
 * `currency` string that came along with the document and defaulted to the
 * literal 'SAR' when there was none. Three different literals were being
 * invented across the codebase ('SAR' in invoices and expenses, 'SYP' in the
 * sales workflow) while the configured base was a fourth. So the label on an
 * entry said whatever the last programmer had typed, and the one thing it did
 * not reliably say was what the numbers meant.
 *
 * Two columns make the invariant explicit instead of assumed:
 *
 *  - `base_currency` — the currency the amounts are in, read from the
 *    configuration at posting time. It is stamped per entry rather than looked
 *    up later because the base can be changed, and an entry posted under the
 *    old one must not start claiming the new.
 *  - `exchange_rate` — what the document's own currency was converted at. It
 *    is 1 for everything today, and that is the honest value: nothing converts
 *    on the way into the ledger. The column exists so that the day foreign
 *    postings are supported, the entries that predate them are not silently
 *    reinterpreted.
 *
 * Existing rows are backfilled with the current base and a rate of 1, which is
 * exactly how they were posted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_headers', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entry_headers', 'base_currency')) {
                $table->string('base_currency', 10)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('journal_entry_headers', 'exchange_rate')) {
                $table->decimal('exchange_rate', 20, 8)->default(1)->after('base_currency');
            }
        });

        $base = $this->baseCode();

        DB::table('journal_entry_headers')
            ->whereNull('base_currency')
            ->update(['base_currency' => $base, 'exchange_rate' => 1]);
    }

    /**
     * The configured base, read straight from the table.
     *
     * Deliberately not through CurrencyService: a migration runs against a
     * schema that may predate the service's assumptions, and the fallback below
     * matches its own.
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
        Schema::table('journal_entry_headers', function (Blueprint $table) {
            foreach (['base_currency', 'exchange_rate'] as $column) {
                if (Schema::hasColumn('journal_entry_headers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
