<?php

namespace App\Console\Commands;

use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Integrity check for the general ledger.
 *
 * Three ways the books can go wrong silently, all of which existed in this
 * database before automatic posting was introduced:
 *
 *  1. An entry whose own lines do not balance.
 *  2. The whole ledger not balancing (debits ≠ credits).
 *  3. An account's cached `balance` column drifting away from its journal
 *     lines — the column is maintained incrementally, so any direct SQL edit
 *     or failed transaction leaves it wrong while every report keeps trusting it.
 *
 * `--fix-balances` recomputes the cached balances from the journal, which is
 * always safe: the journal is the source of truth. Unbalanced entries are
 * reported but never auto-corrected — only a human knows the missing amount.
 */
class AccountingCheck extends Command
{
    protected $signature = 'accounting:check {--fix-balances : Recompute cached account balances from the journal}';

    protected $description = 'Verify general-ledger integrity (entry balance, ledger balance, cached account balances)';

    private const EPSILON = 0.005;

    public function handle(): int
    {
        $problems = 0;

        $problems += $this->checkSourceDocuments();
        $problems += $this->checkReferentialIntegrity();
        $problems += $this->checkUnbalancedEntries();
        $problems += $this->checkLedgerTotals();
        $problems += $this->checkAccountBalances();

        $this->newLine();

        if ($problems === 0) {
            $this->info('✔ الدفاتر سليمة — لا توجد مشاكل.');
            return self::SUCCESS;
        }

        $this->warn("انتهى الفحص: {$problems} مشكلة.");

        return self::FAILURE;
    }

    /**
     * Rows that point at something no longer there, and entries with no
     * substance.
     *
     * Foreign keys make most of these impossible going forward, but they were
     * added after the tables were in use, and a restore or a direct SQL edit
     * can still leave one behind. Each is invisible to the other checks: an
     * entry with no lines balances trivially, and a line whose account is gone
     * is simply dropped by every join that reports on it — so the difference
     * appears in the totals and nowhere else.
     */
    private function checkReferentialIntegrity(): int
    {
        $this->line('— سلامة الروابط');

        $orphanLines = DB::table('journal_entry_lines as l')
            ->leftJoin('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
            ->whereNull('h.id')
            ->count();

        $missingAccounts = DB::table('journal_entry_lines as l')
            ->leftJoin('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereNull('a.id')
            ->count();

        $emptyEntries = DB::table('journal_entry_headers as h')
            ->whereNull('h.deleted_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('journal_entry_lines as l')
                ->whereColumn('l.journal_entry_header_id', 'h.id'))
            ->count();

        // The unique index makes this impossible now; it was added to a table
        // that already had rows, so a duplicate from before it would have been
        // rejected at index creation — this confirms none slipped through a
        // later restore.
        $duplicateKeys = DB::table('journal_entry_headers')
            ->whereNotNull('posting_key')
            ->groupBy('posting_key')
            ->havingRaw('COUNT(*) > 1')
            ->select('posting_key')
            ->get()
            ->count();

        $rows = array_filter([
            $orphanLines ? ['سطور قيد بلا رأس', $orphanLines] : null,
            $missingAccounts ? ['سطور تشير إلى حساب محذوف', $missingAccounts] : null,
            $emptyEntries ? ['قيود بلا سطور', $emptyEntries] : null,
            $duplicateKeys ? ['مفاتيح ترحيل مكررة', $duplicateKeys] : null,
        ]);

        if ($rows === []) {
            $this->info('  كل الروابط سليمة.');

            return 0;
        }

        $this->table(['المشكلة', 'العدد'], array_values($rows));
        $this->warn('  هذه لا تُصلَح آلياً: كل حالة تحتاج قراراً عن القيد الذي تخصه.');

        return array_sum(array_column($rows, 1));
    }

    /**
     * Invoices whose own header arithmetic does not hold.
     *
     * Checked first because the ledger can only be as correct as what feeds it:
     * an invoice where subtotal + tax - discount ≠ total produces a lopsided
     * posting no matter how careful the posting code is.
     */
    private function checkSourceDocuments(): int
    {
        $this->line('— اتساق المستندات المصدر (الفواتير)');

        // Filtered with WHERE, not HAVING: there is no GROUP BY here, and under
        // ONLY_FULL_GROUP_BY MySQL rejects a HAVING clause that references a
        // non-aggregated column.
        // `invoices` has no soft-delete column, so filtering on one made MySQL
        // answer every run of this command with "Unknown column 'deleted_at'"
        // — the integrity check could not be run at all. SQLite would have read
        // the quoted name as a string literal and passed, which is why it went
        // unnoticed.
        $bad = DB::table('invoices')
            ->when(
                DB::getSchemaBuilder()->hasColumn('invoices', 'deleted_at'),
                fn ($q) => $q->whereNull('deleted_at')
            )
            ->whereRaw('ABS(ROUND(subtotal + tax + additional_charges - discount, 2) - total) > ?', [self::EPSILON])
            ->selectRaw('id, invoice_number, subtotal, tax, discount, additional_charges, total,
                         ROUND(subtotal + tax + additional_charges - discount, 2) as computed')
            ->get();

        if ($bad->isEmpty()) {
            $this->info('  جميع الفواتير متسقة.');
            return 0;
        }

        $this->table(
            ['الفاتورة', 'المجموع الفرعي', 'رسوم إضافية', 'الضريبة', 'الخصم', 'الإجمالي المسجَّل', 'الإجمالي المحسوب'],
            $bad->map(fn ($i) => [
                $i->invoice_number,
                number_format((float) $i->subtotal, 2),
                number_format((float) $i->additional_charges, 2),
                number_format((float) $i->tax, 2),
                number_format((float) $i->discount, 2),
                number_format((float) $i->total, 2),
                number_format((float) $i->computed, 2),
            ])->all()
        );

        $this->warn('  الرقم الصحيح قرار تجاري — لا يصححه النظام تلقائياً.');

        return $bad->count();
    }

    private function checkUnbalancedEntries(): int
    {
        $this->line('— القيود غير المتوازنة');

        $entries = JournalEntryHeader::query()
            ->withSum('lines as sum_debit', 'debit')
            ->withSum('lines as sum_credit', 'credit')
            ->get()
            ->filter(fn ($h) => abs((float) $h->sum_debit - (float) $h->sum_credit) > self::EPSILON);

        if ($entries->isEmpty()) {
            $this->info('  لا يوجد.');
            return 0;
        }

        $this->table(
            ['القيد', 'التاريخ', 'مدين', 'دائن', 'الفرق'],
            $entries->map(fn ($h) => [
                $h->entry_number,
                optional($h->entry_date)->toDateString(),
                number_format((float) $h->sum_debit, 2),
                number_format((float) $h->sum_credit, 2),
                number_format((float) $h->sum_debit - (float) $h->sum_credit, 2),
            ])->all()
        );

        $this->warn('  هذه القيود تكسر ميزان المراجعة وتحتاج تصحيحاً يدوياً.');

        return $entries->count();
    }

    private function checkLedgerTotals(): int
    {
        $this->newLine();
        $this->line('— توازن الدفتر ككل');

        $totals = DB::table('journal_entry_lines as l')
            ->join('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
            ->whereNull('h.deleted_at')
            ->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')
            ->first();

        $difference = round((float) $totals->d - (float) $totals->c, 2);

        $this->line('  مدين: ' . number_format((float) $totals->d, 2));
        $this->line('  دائن: ' . number_format((float) $totals->c, 2));

        if (abs($difference) < self::EPSILON) {
            $this->info('  متوازن.');
            return 0;
        }

        $this->error('  غير متوازن بفارق ' . number_format($difference, 2));

        return 1;
    }

    private function checkAccountBalances(): int
    {
        $this->newLine();
        $this->line('— الأرصدة المخزّنة مقابل دفتر اليومية');

        $rows = DB::table('ledger_accounts as a')
            ->leftJoin('journal_entry_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entry_headers as h', function ($join) {
                $join->on('h.id', '=', 'l.journal_entry_header_id')->whereNull('h.deleted_at');
            })
            ->selectRaw('a.id, a.code, a.name, a.type, a.balance, a.opening_balance,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.debit END),0) d,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.credit END),0) c')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.balance', 'a.opening_balance')
            ->orderBy('a.code')
            ->get();

        $drifted = [];

        foreach ($rows as $row) {
            $expected = round(
                (float) $row->opening_balance
                + LedgerAccount::signedDelta($row->type, (float) $row->d, (float) $row->c),
                2
            );
            $stored = round((float) $row->balance, 2);

            if (abs($expected - $stored) > self::EPSILON) {
                $drifted[] = [$row->code, $row->name, number_format($stored, 2), number_format($expected, 2), $row->id, $expected];
            }
        }

        if (!$drifted) {
            $this->info('  جميع الأرصدة مطابقة.');
            return 0;
        }

        $this->table(
            ['الحساب', 'الاسم', 'المخزّن', 'الصحيح'],
            array_map(fn ($d) => array_slice($d, 0, 4), $drifted)
        );

        if ($this->option('fix-balances')) {
            foreach ($drifted as [, , , , $id, $expected]) {
                DB::table('ledger_accounts')->where('id', $id)->update(['balance' => $expected]);
            }
            $this->info('  تم تصحيح ' . count($drifted) . ' رصيد من واقع دفتر اليومية.');
            return 0;
        }

        $this->warn('  شغّل الأمر مع --fix-balances لإعادة احتسابها من دفتر اليومية.');

        return count($drifted);
    }
}
