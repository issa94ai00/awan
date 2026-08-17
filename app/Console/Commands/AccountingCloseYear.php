<?php

namespace App\Console\Commands;

use App\Models\LedgerAccount;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Closes a year's result into retained earnings.
 *
 * Revenue and expense accounts measure a period, not a position: they answer
 * "what happened between these dates", and the answer has to stop at the year
 * end so the next year starts from nothing. Until they are closed they keep
 * accumulating, and an income statement run in the second year silently
 * includes the first.
 *
 * The balance sheet has been carrying the unclosed result as its own equity
 * line — an honest presentation of a year that is still open, and exactly what
 * this replaces with a real entry:
 *
 *   Dr  each revenue account   its balance
 *       Cr  each expense account     its balance
 *       Cr  Retained earnings        the profit  (or Dr, for a loss)
 *
 * The 3002 account has existed since the posting foundation was laid and has
 * never received a single line; this is what it was created for.
 *
 * Dated the last day of the year being closed, so the result belongs to the
 * year that earned it. That also means the year must still be open in the
 * period lock — closing the books and closing the period are different acts,
 * and doing them in the wrong order refuses the entry.
 */
class AccountingCloseYear extends Command
{
    protected $signature = 'accounting:close-year
                            {year : The year to close, e.g. 2026}
                            {--dry-run : Report the entry and write nothing}';

    protected $description = 'Close revenue and expense accounts of a year into retained earnings';

    private const UNPOSTED_STATUSES = ['draft', 'pending', 'void', 'cancelled'];

    private const EPSILON = 0.005;

    public function handle(LedgerPostingService $ledger): int
    {
        $year = (int) $this->argument('year');

        if ($year < 2000 || $year > 2100) {
            $this->error('سنة غير معقولة.');

            return self::FAILURE;
        }

        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);

        $movements = $this->movements($from, $to);

        if ($movements->isEmpty()) {
            $this->warn("لا توجد حركة على حسابات الإيرادات والمصروفات في {$year}.");

            return self::SUCCESS;
        }

        $lines = [];
        $revenue = 0.0;
        $expense = 0.0;
        $rows = [];

        foreach ($movements as $row) {
            $balance = round(
                LedgerAccount::signedDelta($row->type, (float) $row->d, (float) $row->c),
                2
            );

            if (abs($balance) < self::EPSILON) {
                continue;
            }

            // Closing an account means posting the opposite of its balance, so
            // whatever side it sits on ends at zero.
            $lines[] = [
                'account_id' => $row->id,
                'debit' => $row->type === 'revenue' ? max($balance, 0) : max(-$balance, 0),
                'credit' => $row->type === 'revenue' ? max(-$balance, 0) : max($balance, 0),
                'description' => 'إقفال '.$row->name.' - '.$year,
            ];

            $rows[] = [$row->code, $row->name, number_format($balance, 2)];

            if ($row->type === 'revenue') {
                $revenue += $balance;
            } else {
                $expense += $balance;
            }
        }

        $result = round($revenue - $expense, 2);

        if ($lines === []) {
            $this->warn('لا يوجد ما يُقفل — كل حسابات النتيجة على صفر.');

            return self::SUCCESS;
        }

        // The profit closes to retained earnings: credit for a profit, debit
        // for a loss. This is the line that makes the entry balance.
        $lines[] = [
            'role' => 'retained_earnings',
            'debit' => max(-$result, 0),
            'credit' => max($result, 0),
            'description' => ($result >= 0 ? 'أرباح ' : 'خسائر ').$year,
        ];

        $this->table(['الحساب', 'الاسم', 'الرصيد المُقفل'], $rows);
        $this->line('الإيرادات: '.number_format($revenue, 2));
        $this->line('المصروفات: '.number_format($expense, 2));
        $this->info(($result >= 0 ? 'صافي ربح: ' : 'صافي خسارة: ').number_format(abs($result), 2));

        if ($this->option('dry-run')) {
            $this->warn('معاينة فقط — لم يُكتب أي قيد.');

            return self::SUCCESS;
        }

        $entry = $ledger->post(
            // One key per year: closing the same year twice is a no-op rather
            // than a second entry that doubles retained earnings.
            key: 'year_close:'.$year,
            date: $to,
            description: 'قيد إقفال السنة المالية '.$year,
            lines: $lines,
            module: 'closing',
        );

        if (! $entry) {
            $this->error('تعذّر ترحيل قيد الإقفال.');

            return self::FAILURE;
        }

        if (! $entry->wasRecentlyCreated) {
            $this->warn('السنة '.$year.' مُقفلة مسبقاً بالقيد '.$entry->entry_number.' — لم يتغير شيء.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('تم ترحيل قيد الإقفال '.$entry->entry_number.'.');
        $this->line('يُنصح الآن بإقفال فترات السنة من شاشة الفترات المحاسبية.');

        return self::SUCCESS;
    }

    /** Revenue and expense movement within the year. */
    private function movements(string $from, string $to)
    {
        return DB::table('ledger_accounts as a')
            ->join('journal_entry_lines as l', 'l.account_id', '=', 'a.id')
            ->join('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
            ->whereIn('a.type', ['revenue', 'expense'])
            ->whereNull('h.deleted_at')
            ->whereNotIn('h.status', self::UNPOSTED_STATUSES)
            ->whereBetween(DB::raw('DATE(h.entry_date)'), [$from, $to])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->selectRaw('a.id, a.code, a.name, a.type,
                         COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')
            ->orderBy('a.code')
            ->get();
    }
}
