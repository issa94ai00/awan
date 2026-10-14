<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * One-off cleanup for the notifications the hourly `alerts:check --notify`
 * piled up before its alerts were keyed: the same "low stock" or "pending
 * orders" message, unread, once an hour for days.
 *
 * Per user and alert, the newest unread copy is kept and given its alert
 * key, so later scans refresh it instead of adding a new one; older unread
 * copies are deleted. Read copies are left alone.
 */
class CollapseDuplicateAlerts extends Command
{
    protected $signature = 'notifications:collapse-alerts {--dry-run : Report what would change without changing it}';

    protected $description = 'Collapse duplicated operational alert notifications into one per user';

    /** Titles alerts:check used before keys existed => the key it uses now. */
    protected const LEGACY_TITLES = [
        'تنبيه نقص ونفاد المخزون' => 'ops.stock',
        'طلبات بيع بانتظار الاعتماد' => 'ops.pending_sales_orders',
        'فواتير مبيعات متأخرة السداد' => 'ops.overdue_invoices',
        'أوامر شراء بانتظار استلام البضاعة' => 'ops.purchase_receipts_due',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;
        $kept = 0;

        foreach (self::LEGACY_TITLES as $title => $key) {
            $userIds = Notification::where('title', $title)->unread()->distinct()->pluck('user_id');

            foreach ($userIds as $userId) {
                $copies = Notification::where('user_id', $userId)
                    ->where('title', $title)
                    ->unread()
                    ->orderByDesc('id')
                    ->get();

                $newest = $copies->shift();
                $kept++;
                $deleted += $copies->count();

                if ($dryRun) {
                    continue;
                }

                $newest->update(['data' => array_merge($newest->data ?? [], ['alert_key' => $key])]);
                Notification::whereIn('id', $copies->pluck('id'))->delete();
            }
        }

        $this->info(($dryRun ? '[dry run] ' : '') . "Kept {$kept} alert notification(s), removed {$deleted} duplicate(s).");

        return self::SUCCESS;
    }
}
