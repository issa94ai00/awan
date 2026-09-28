<?php

namespace App\Console\Commands;

use App\Services\Accounting\FixedAssetDepreciation;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Charges depreciation across the register up to a month, catching up any
 * month that was missed.
 *
 * Meant to be run once a month, and safe to run again: each asset's charge for
 * a given month is posted under a key naming that month, so a second run finds
 * the entry already there and changes nothing. That matters more than it
 * sounds — depreciation is the one accounting event with no document behind it
 * to notice a duplicate, and a month charged twice quietly halves the profit.
 *
 * Straight-line only. It is what the register's fields describe, and a method
 * the system cannot explain to the person reading the statement is worse than
 * one that is simple.
 */
class AccountingDepreciate extends Command
{
    protected $signature = 'accounting:depreciate
                            {--month= : Charge up to and including this month, as YYYY-MM (defaults to last month)}
                            {--dry-run : Report the charges and post nothing}';

    protected $description = 'Post straight-line depreciation for every active asset up to a month, including missed months';

    public function handle(FixedAssetDepreciation $depreciation): int
    {
        $month = $this->resolveMonth();

        if (! $month) {
            $this->error('صيغة الشهر غير صحيحة — استخدم YYYY-MM.');

            return self::FAILURE;
        }

        // A month that has not ended yet has not been used up yet.
        if ($month->copy()->endOfMonth()->isFuture()) {
            $this->warn('الشهر '.$month->format('Y-m').' لم ينتهِ بعد — الإهلاك يُحتسب عن شهر مكتمل.');

            return self::SUCCESS;
        }

        // Every month still owed up to this one, not only this one: a month
        // the schedule missed would otherwise never be charged.
        $preview = $depreciation->preview($month);

        if ($preview->isEmpty()) {
            $this->info('لا شيء لإهلاكه حتى '.$month->format('Y-m').' — كل الأصول محدَّثة أو مُهلكة بالكامل.');

            return self::SUCCESS;
        }

        $this->table(
            ['الأصل', 'الاسم', 'الأشهر', 'القسط', 'القيمة الدفترية بعده'],
            $preview->map(fn (array $row) => [
                $row['asset_number'],
                mb_substr($row['name'], 0, 28),
                implode(', ', $row['months']),
                number_format($row['amount'], 2),
                number_format($row['net_book_value_after'], 2),
            ])->all()
        );
        $this->line('إجمالي الإهلاك حتى '.$month->format('Y-m').': '.number_format($preview->sum('amount'), 2));

        if ($this->option('dry-run')) {
            $this->warn('معاينة فقط — لم يُرحَّل أي قيد.');

            return self::SUCCESS;
        }

        $result = $depreciation->run($month);

        foreach ($result['blocked'] as $blocked) {
            $this->warn($blocked['asset_number'].': '.$blocked['reason']);
        }

        $this->info("تم ترحيل {$result['entries']} قيد إهلاك.");

        return self::SUCCESS;
    }

    /** Defaults to the month just ended, which is the one being closed. */
    private function resolveMonth(): ?Carbon
    {
        $option = $this->option('month');

        if (! $option) {
            return FixedAssetDepreciation::lastCompletedMonth();
        }

        if (! preg_match('/^(\d{4})-(\d{2})$/', (string) $option, $m)) {
            return null;
        }

        if ((int) $m[2] < 1 || (int) $m[2] > 12) {
            return null;
        }

        return Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();
    }
}
