<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckOperationalAlerts extends Command
{
    protected $signature = 'alerts:check {--notify : Dispatch notifications to system administrators}';

    protected $description = 'Scan operational ERP metrics (stock, pending orders, overdue invoices) and dispatch alerts';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Runs hourly. Each alert has a fixed key, so with --notify an unresolved
     * problem keeps one notification per admin up to date instead of adding
     * a new one every hour, and a problem that has cleared has its unread
     * notification marked read (see NotificationService::raiseAlert).
     */
    public function handle(): int
    {
        $this->info('Starting operational scan for system alerts...');

        $alertCount = 0;

        // 1. Check Out of Stock & Low Stock
        $outOfStockCount = Product::where('stock_quantity', '<=', 0)->count();
        $lowStockCount = Product::whereNotNull('min_stock')
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->count();

        if ($outOfStockCount > 0 || $lowStockCount > 0) {
            $alertCount++;
            $this->warn("Inventory Alert: {$outOfStockCount} products out of stock, {$lowStockCount} below minimum.");
        }
        $this->raiseOrResolve(
            'ops.stock',
            $outOfStockCount > 0 || $lowStockCount > 0,
            'تنبيه نقص ونفاد المخزون',
            "يوجد {$outOfStockCount} صنف نافد من المخزون و {$lowStockCount} صنف بحاجة لإعادة طلب.",
            'inventory',
            ['route' => '/admin/stock', 'out_of_stock' => $outOfStockCount, 'low_stock' => $lowStockCount]
        );

        // 2. Check Pending Sales Orders
        $pendingOrders = SalesOrder::where('status', 'pending')->count();
        if ($pendingOrders > 0) {
            $alertCount++;
            $this->warn("Sales Orders Alert: {$pendingOrders} orders pending confirmation.");
        }
        $this->raiseOrResolve(
            'ops.pending_sales_orders',
            $pendingOrders > 0,
            'طلبات بيع بانتظار الاعتماد',
            "يوجد {$pendingOrders} طلب بيع معلق بحاجة لموافقة أو تأكيد فوري.",
            'order',
            ['route' => '/admin/sales/sales-orders?status=pending', 'count' => $pendingOrders]
        );

        // 3. Check Overdue Invoices
        $overdueInvoices = Invoice::where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->where('due_date', '<', now())
            ->count();
        if ($overdueInvoices > 0) {
            $alertCount++;
            $this->error("Financial Alert: {$overdueInvoices} invoices are overdue.");
        }
        $this->raiseOrResolve(
            'ops.overdue_invoices',
            $overdueInvoices > 0,
            'فواتير مبيعات متأخرة السداد',
            "يوجد {$overdueInvoices} فاتورة تجاوزت موعد الاستحقاق دون سداد كامل.",
            'financial',
            ['route' => '/admin/sales/invoices?status=unpaid', 'count' => $overdueInvoices]
        );

        // 4. Check Confirmed Purchases Awaiting Receipt
        $awaitingReceipt = PurchaseOrder::where('status', 'confirmed')->count();
        if ($awaitingReceipt > 0) {
            $alertCount++;
            $this->line("Warehouse Alert: {$awaitingReceipt} confirmed purchase orders awaiting receipt.");
        }
        $this->raiseOrResolve(
            'ops.purchase_receipts_due',
            $awaitingReceipt > 0,
            'أوامر شراء بانتظار استلام البضاعة',
            "يوجد {$awaitingReceipt} أمر شراء مؤكد بانتظار استلام البضاعة في المستودع.",
            'warehouse',
            ['route' => '/admin/purchases/receipts', 'count' => $awaitingReceipt]
        );

        $this->newLine();
        if ($alertCount === 0) {
            $this->info('✔ All operational systems are normal — no alerts detected.');
        } else {
            $this->info("Scan completed: {$alertCount} active operational alerts detected." . ($this->option('notify') ? ' Notifications dispatched.' : ' Run with --notify to dispatch.'));
        }

        return self::SUCCESS;
    }

    protected function raiseOrResolve(string $key, bool $active, string $title, string $message, string $type, array $data): void
    {
        if (!$this->option('notify')) {
            return;
        }

        if ($active) {
            $this->notificationService->raiseAlert($key, $title, $message, $type, $data);
        } else {
            $this->notificationService->resolveAlert($key);
        }
    }
}
