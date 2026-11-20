<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// `sitemap:generate` lives in App\Console\Commands\GenerateSitemap.
// Defining it here as well would register the same signature twice.

// Schedule sitemap generation
Schedule::command('sitemap:generate')->daily();

// Nightly database backup, kept for 14 days.
Schedule::command('db:backup')
    ->dailyAt('05:00')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| The monthly accounting run
|--------------------------------------------------------------------------
|
| Two costs are earned by the passage of time rather than by a document: an
| asset is used up a month at a time, and an end-of-service benefit is earned
| by working. Every other entry in this system is triggered by something
| somebody did — an invoice, a payment, a delivery — so a missed one is
| noticed when the document it belongs to has no entry.
|
| These two have no document behind them. Forgetting them produces no error
| and no gap anybody can see: profit is simply overstated, month after month,
| by a cost that was never charged. That is exactly the kind of omission a
| schedule exists for.
|
| Both run on the first of the month, for the month that has just ended — the
| commands default to the previous month, and each refuses to charge a month
| it has already charged, so a retry or a double run changes nothing.
|
| They are quiet by design when there is nothing to do: no active assets and
| no employees with a salary on record means no entries, not an error.
|
*/
Schedule::command('accounting:depreciate')
    ->monthlyOn(1, '01:30')
    ->withoutOverlapping();

Schedule::command('accounting:accrue-end-of-service')
    ->monthlyOn(1, '01:45')
    ->withoutOverlapping();

// Trash retention for commission statements/withdrawals: 90 days to review
// or restore a soft-deleted record, then it is gone for good.
Schedule::command('commissions:purge-trashed')
    ->dailyAt('02:15')
    ->withoutOverlapping();

// Sent quotes past valid_until can no longer be accepted; close them so the
// follow-up list and conversion rate reflect live offers only.
Schedule::command('quotes:expire')
    ->dailyAt('00:30')
    ->withoutOverlapping();

// Bot visits older than Visitor::BOT_RETENTION_DAYS (human visits are kept).
Schedule::command('model:prune', ['--model' => [\App\Models\Visitor::class]])
    ->dailyAt('02:45')
    ->withoutOverlapping();

// Ledger, stock and customer-balance integrity; alerts admins on drift.
Schedule::command('db:integrity-check --notify')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/integrity.log'));

// Operational ERP metrics alert scan (stock shortages, pending orders, overdue invoices)
Schedule::command('alerts:check --notify')
    ->hourly()
    ->withoutOverlapping();


// Read notifications older than 90 days are pruned; unread ones are kept.
Schedule::call(fn () => app(\App\Services\NotificationService::class)->deleteOldNotifications(90))
    ->name('notifications:prune-read')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Daily system-health snapshot: feeds the score trend on /admin/system-health
// even when nobody presses "run". Read-only checks, off-peak.
Schedule::command('diagnostics:run')
    ->dailyAt('03:30')
    ->withoutOverlapping();
