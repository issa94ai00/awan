<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Daily guard over the caches the books and the shelves depend on.
 *
 * accounting:check, inventory:check and customers:rebuild-balance --dry-run
 * each fail when their figures drift from the documents behind them. Run by
 * hand they only help if somebody remembers to; this runs them together and,
 * with --notify, raises one admin alert per failing check (resolved again on
 * the next clean run), so drift is seen the day it starts.
 */
class DbIntegrityCheck extends Command
{
    protected $signature = 'db:integrity-check {--notify : Raise or resolve admin alerts}';

    protected $description = 'Run the ledger, stock and customer-balance integrity checks';

    private const CHECKS = [
        'accounting' => ['accounting:check', [], 'الدفاتر المحاسبية'],
        'inventory' => ['inventory:check', [], 'المخزون'],
        'customer_balances' => ['customers:rebuild-balance', ['--dry-run' => true], 'أرصدة العملاء'],
    ];

    public function handle(NotificationService $notifications): int
    {
        $failed = [];

        // Stamped so the scheduled log (storage/logs/integrity.log) shows when each run happened.
        $this->line('== '.now()->toDateTimeString().' '.config('app.timezone'));

        foreach (self::CHECKS as $key => [$command, $args, $label]) {
            $ok = Artisan::call($command, $args) === self::SUCCESS;
            $this->line(($ok ? '✔ ' : '✘ ').$label);

            if (! $ok) {
                $failed[] = $label;
            }

            if ($this->option('notify')) {
                $alertKey = "db_integrity:{$key}";
                $ok
                    ? $notifications->resolveAlert($alertKey)
                    : $notifications->raiseAlert(
                        $alertKey,
                        'خلل في سلامة البيانات: '.$label,
                        "فشل الفحص اليومي ({$command}). شغّله يدوياً لمعرفة التفاصيل.",
                        'system',
                        ['command' => $command]
                    );
            }
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
