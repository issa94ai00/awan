<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\Accounting\LedgerPostingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Charges one month of end-of-service benefit for everybody still working.
 *
 * The benefit is earned by working, so it is recognised month by month rather
 * than in one lump on the day somebody leaves. Until that happens the balance
 * sheet says nothing about a debt the business has already run up, and the
 * month of a departure carries years of cost that belonged to other periods.
 *
 * One month of salary per year of service, which is the common entitlement
 * here: the monthly share is a twelfth of the salary. Businesses whose contracts
 * say otherwise should change the rate rather than the timing — the timing is
 * what makes the figure honest.
 *
 * Safe to re-run: each employee's accrual for a month is posted under a key
 * naming that month. Like depreciation, this has no document behind it to
 * notice a duplicate, and a month charged twice overstates a liability nobody
 * would think to check.
 */
class AccountingAccrueEndOfService extends Command
{
    protected $signature = 'accounting:accrue-end-of-service
                            {--month= : The month to accrue, as YYYY-MM (defaults to last month)}
                            {--rate=12 : Months of salary the yearly entitlement is divided by}
                            {--dry-run : Report the accruals and post nothing}';

    protected $description = 'Accrue one month of end-of-service benefit for active employees';

    public function handle(LedgerPostingService $ledger): int
    {
        $month = $this->resolveMonth();

        if (! $month) {
            $this->error('صيغة الشهر غير صحيحة — استخدم YYYY-MM.');

            return self::FAILURE;
        }

        if ($month->copy()->endOfMonth()->isFuture()) {
            $this->warn('الشهر '.$month->format('Y-m').' لم ينتهِ بعد — الاستحقاق يُحتسب عن شهر عمل مكتمل.');

            return self::SUCCESS;
        }

        $divisor = max(1, (int) $this->option('rate'));

        $employees = Employee::where('status', 'active')
            ->where('salary', '>', 0)
            ->whereDate('hire_date', '<=', $month->copy()->endOfMonth())
            ->orderBy('id')
            ->get();

        if ($employees->isEmpty()) {
            $this->info('لا يوجد موظفون نشطون يُستحق لهم في '.$month->format('Y-m').'.');

            return self::SUCCESS;
        }

        $rows = [];
        $total = 0.0;
        $posted = 0;

        foreach ($employees as $employee) {
            // Already charged for this month.
            if ($employee->end_of_service_through
                && Carbon::parse($employee->end_of_service_through)->gte($month->copy()->endOfMonth()->startOfDay())) {
                continue;
            }

            $share = round((float) $employee->salary / $divisor, 2);

            if ($share <= 0) {
                continue;
            }

            $name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) ?: ('#'.$employee->id);

            $rows[] = [
                $employee->id,
                mb_substr($name, 0, 26),
                number_format((float) $employee->salary, 2),
                number_format($share, 2),
                number_format((float) $employee->end_of_service_accrued + $share, 2),
            ];

            $total += $share;

            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($ledger, $employee, $month, $share, &$posted) {
                $entry = $ledger->postEndOfServiceAccrual($employee, $month, $share);

                if (! $entry?->wasRecentlyCreated) {
                    return;
                }

                $employee->forceFill([
                    'end_of_service_accrued' => round((float) $employee->end_of_service_accrued + $share, 2),
                    'end_of_service_through' => $month->copy()->endOfMonth()->toDateString(),
                ])->save();

                $posted++;
            });
        }

        if ($rows === []) {
            $this->info('لا شيء يُستحق في '.$month->format('Y-m').' — الجميع محدَّثون.');

            return self::SUCCESS;
        }

        $this->table(['#', 'الموظف', 'الراتب', 'استحقاق الشهر', 'المجمَّع بعده'], $rows);
        $this->line('إجمالي استحقاق '.$month->format('Y-m').': '.number_format($total, 2));

        if ($this->option('dry-run')) {
            $this->warn('معاينة فقط — لم يُرحَّل أي قيد.');

            return self::SUCCESS;
        }

        $this->info("تم ترحيل {$posted} قيد استحقاق.");

        return self::SUCCESS;
    }

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
