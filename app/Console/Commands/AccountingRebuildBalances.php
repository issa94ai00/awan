<?php

namespace App\Console\Commands;

use App\Models\LedgerAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds every account balance from the journal lines behind it.
 *
 * `ledger_accounts.balance` is a cache. The journal is the record: an account's
 * balance *is* the sum of its lines, and the column exists only so a screen
 * does not have to add up a year of them to show one number.
 *
 * Treated as a second source of truth it becomes a liability. It is maintained
 * by increments, so anything that writes a line without going through the
 * posting service — an import, a hand-run SQL statement, a restore from a
 * partial backup — leaves the two disagreeing, and every report picks whichever
 * one it happens to read. `accounting:check` reports the drift; this is what
 * ends it.
 *
 * Safe to run at any time: it derives, it does not invent. Nothing about the
 * journal changes, so running it twice gives the same answer as running it once.
 */
class AccountingRebuildBalances extends Command
{
    protected $signature = 'accounting:rebuild-balances
                            {--dry-run : Report the drift and change nothing}';

    protected $description = 'Recompute every ledger account balance from its journal lines';

    /** Entry statuses that never reached the books. */
    private const UNPOSTED_STATUSES = ['draft', 'pending', 'void', 'cancelled'];

    private const EPSILON = 0.005;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // One grouped query rather than a pass per account: the whole point of
        // the cache is that summing lines is expensive, so the repair should
        // not do it several hundred times.
        $sums = DB::table('journal_entry_lines as l')
            ->join('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
            ->whereNull('h.deleted_at')
            ->whereNotIn('h.status', self::UNPOSTED_STATUSES)
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')
            ->get()
            ->keyBy('account_id');

        $drifted = [];
        $repaired = 0;

        foreach (LedgerAccount::orderBy('code')->get() as $account) {
            $row = $sums->get($account->id);

            $derived = round(LedgerAccount::signedDelta(
                $account->type,
                (float) ($row->d ?? 0),
                (float) ($row->c ?? 0)
            ), 2);

            $stored = round((float) $account->balance, 2);

            if (abs($derived - $stored) < self::EPSILON) {
                continue;
            }

            $drifted[] = [
                $account->code,
                $account->name,
                number_format($stored, 2),
                number_format($derived, 2),
                number_format($derived - $stored, 2),
            ];

            if (! $dryRun) {
                $account->update(['balance' => $derived]);
                $repaired++;
            }
        }

        if ($drifted === []) {
            $this->info('كل الأرصدة مطابقة لسطور اليومية — لا شيء لإصلاحه.');

            return self::SUCCESS;
        }

        $this->table(['الحساب', 'الاسم', 'المخزَّن', 'المحسوب', 'الفرق'], $drifted);

        if ($dryRun) {
            $this->warn('معاينة فقط — لم يُعدَّل أي رصيد. أعد التشغيل بلا ‎--dry-run‎ للإصلاح.');

            return self::SUCCESS;
        }

        $this->info("أُعيد بناء {$repaired} رصيداً من دفتر اليومية.");

        return self::SUCCESS;
    }
}
