<?php

namespace App\Console\Commands;

use App\Models\EmployeeCommission;
use App\Models\EmployeeCommissionWithdrawal;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Hard-deletes commission statements and withdrawals that have sat in the
 * trash past the retention window. Withdrawals go first — a commission row
 * still holding trashed withdrawal children would otherwise leave orphaned
 * rows behind once the parent is force-deleted.
 */
class PurgeTrashedCommissionRecords extends Command
{
    protected $signature = 'commissions:purge-trashed {--days=90} {--dry-run}';

    protected $description = 'Permanently deletes employee commission statements and withdrawals soft-deleted more than N days ago';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays((int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        $withdrawals = EmployeeCommissionWithdrawal::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $withdrawalCount = $withdrawals->count();

        $commissions = EmployeeCommission::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $commissionCount = $commissions->count();

        if ($dryRun) {
            $this->info("Would purge {$withdrawalCount} withdrawal(s) and {$commissionCount} commission statement(s) deleted before {$cutoff->toDateTimeString()}.");
            return self::SUCCESS;
        }

        $withdrawals->get()->each->forceDelete();
        $commissions->get()->each->forceDelete();

        $this->info("Purged {$withdrawalCount} withdrawal(s) and {$commissionCount} commission statement(s) deleted before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
