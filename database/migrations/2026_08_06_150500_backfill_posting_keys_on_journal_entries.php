<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfills `posting_key` on journal entries that already reference a document.
 *
 * Five invoice entries were posted by hand before automatic posting existed.
 * They carry reference_type/reference_id but no posting_key, so the posting
 * service — which treats a missing key as "never posted" — would have written a
 * second entry for each of those invoices the next time anything touched them.
 *
 * Only entries that are unambiguous (one per document) are keyed; if a document
 * somehow has several entries the rows are left alone rather than guessing.
 */
return new class extends Migration
{
    /** Model class => posting key prefix used by LedgerPostingService. */
    private const PREFIX_BY_TYPE = [
        'App\Models\Invoice' => 'invoice',
        'App\Models\Payment' => 'payment',
        'App\Models\CreditNote' => 'credit_note',
        'App\Models\Expense' => 'expense',
    ];

    public function up(): void
    {
        foreach (self::PREFIX_BY_TYPE as $type => $prefix) {
            $groups = DB::table('journal_entry_headers')
                ->select('reference_id', DB::raw('COUNT(*) as total'), DB::raw('MIN(id) as entry_id'))
                ->where('reference_type', $type)
                ->whereNull('posting_key')
                ->whereNull('deleted_at')
                ->groupBy('reference_id')
                ->get();

            foreach ($groups as $group) {
                if ($group->total > 1 || !$group->reference_id) {
                    continue;
                }

                $key = $prefix . ':' . $group->reference_id;

                // Never clash with a key that already exists.
                if (DB::table('journal_entry_headers')->where('posting_key', $key)->exists()) {
                    continue;
                }

                DB::table('journal_entry_headers')
                    ->where('id', $group->entry_id)
                    ->update(['posting_key' => $key, 'source_module' => 'sales']);
            }
        }
    }

    public function down(): void
    {
        // Keys are derived data; clearing them would reintroduce the duplicate
        // risk, so the rollback intentionally leaves them in place.
    }
};
