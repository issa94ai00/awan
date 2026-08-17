<?php

namespace App\Console\Commands;

use App\Models\FixedAsset;
use App\Services\Accounting\LedgerPostingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Charges one month of depreciation across the register.
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
                            {--month= : The month to charge, as YYYY-MM (defaults to last month)}
                            {--dry-run : Report the charges and post nothing}';

    protected $description = 'Post one month of straight-line depreciation for every active asset';

    public function handle(LedgerPostingService $ledger): int
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

        $assets = FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)
            ->whereDate('acquired_on', '<=', $month->copy()->endOfMonth())
            ->orderBy('asset_number')
            ->get();

        if ($assets->isEmpty()) {
            $this->info('لا توجد أصول نشطة تُهلك في '.$month->format('Y-m').'.');

            return self::SUCCESS;
        }

        $rows = [];
        $total = 0.0;
        $posted = 0;

        foreach ($assets as $asset) {
            if ($asset->isDepreciatedThrough($month)) {
                continue;
            }

            $charge = $asset->chargeFor($month);

            if ($charge <= 0) {
                continue;
            }

            $rows[] = [
                $asset->asset_number,
                mb_substr($asset->name, 0, 28),
                number_format((float) $asset->cost, 2),
                number_format($charge, 2),
                number_format($asset->netBookValue() - $charge, 2),
            ];

            $total += $charge;

            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($ledger, $asset, $month, $charge, &$posted) {
                $entry = $ledger->postDepreciation($asset, $month, $charge);

                if (! $entry?->wasRecentlyCreated) {
                    return;
                }

                // The register carries its own running total so it can be read
                // without replaying the journal; the entry above is the record.
                $asset->update([
                    'accumulated_depreciation' => round((float) $asset->accumulated_depreciation + $charge, 2),
                    'depreciated_through' => $month->copy()->endOfMonth()->toDateString(),
                ]);

                $posted++;
            });
        }

        if ($rows === []) {
            $this->info('لا شيء لإهلاكه في '.$month->format('Y-m').' — كل الأصول محدَّثة أو مُهلكة بالكامل.');

            return self::SUCCESS;
        }

        $this->table(['الأصل', 'الاسم', 'التكلفة', 'قسط الشهر', 'القيمة الدفترية بعده'], $rows);
        $this->line('إجمالي إهلاك '.$month->format('Y-m').': '.number_format($total, 2));

        if ($this->option('dry-run')) {
            $this->warn('معاينة فقط — لم يُرحَّل أي قيد.');

            return self::SUCCESS;
        }

        $this->info("تم ترحيل {$posted} قيد إهلاك.");

        return self::SUCCESS;
    }

    /** Defaults to the month just ended, which is the one being closed. */
    private function resolveMonth(): ?Carbon
    {
        $option = $this->option('month');

        if (! $option) {
            return now()->subMonthNoOverflow()->startOfMonth();
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
