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

    /** A pending sales order older than this has stalled rather than queued. */
    private const STALE_SALES_ORDER_DAYS = 7;

    /** A purchase order with nothing received after this is overdue. */
    private const OVERDUE_PURCHASE_DAYS = 14;

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

        // 5. Sales orders left pending for over a week. "Pending" above is a
        //    queue; these have dropped out of it (forgotten, or waiting on a
        //    supplier nobody is chasing) and hold no stock and no invoice.
        $stalePending = SalesOrder::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(self::STALE_SALES_ORDER_DAYS));
        $staleCount = (clone $stalePending)->count();
        $oldest = $staleCount ? (clone $stalePending)->orderBy('created_at')->first(['order_number', 'created_at']) : null;
        if ($staleCount > 0) {
            $alertCount++;
            $this->warn("Sales Orders Alert: {$staleCount} orders pending for over ".self::STALE_SALES_ORDER_DAYS.' days.');
        }
        $this->raiseOrResolve(
            'ops.stale_sales_orders',
            $staleCount > 0,
            'طلبات بيع معلّقة منذ أكثر من '.self::STALE_SALES_ORDER_DAYS.' أيام',
            $oldest
                ? "يوجد {$staleCount} طلب بيع معلّق منذ أكثر من ".self::STALE_SALES_ORDER_DAYS." أيام، أقدمها {$oldest->order_number} منذ {$oldest->created_at->diffInDays(now())} يوماً. أكّدها أو ألغِها."
                : '',
            'order',
            ['route' => '/admin/sales/sales-orders?status=pending', 'count' => $staleCount]
        );

        // 6. Purchase orders open past the expected lead time with nothing
        //    received. A sales order is often waiting on exactly these.
        $overduePurchases = PurchaseOrder::whereIn('status', ['pending', 'confirmed'])
            ->where('created_at', '<', now()->subDays(self::OVERDUE_PURCHASE_DAYS))
            ->whereDoesntHave('receipts');
        $overduePoCount = (clone $overduePurchases)->count();
        $blockingSales = (clone $overduePurchases)->whereNotNull('sales_order_id')
            ->whereHas('salesOrder', fn ($q) => $q->where('status', 'pending'))->count();
        if ($overduePoCount > 0) {
            $alertCount++;
            $this->warn("Purchasing Alert: {$overduePoCount} purchase orders open for over ".self::OVERDUE_PURCHASE_DAYS.' days with nothing received.');
        }
        $this->raiseOrResolve(
            'ops.overdue_purchase_orders',
            $overduePoCount > 0,
            'أوامر شراء متأخرة دون استلام',
            "يوجد {$overduePoCount} أمر شراء مفتوح منذ أكثر من ".self::OVERDUE_PURCHASE_DAYS.' يوماً دون أي استلام'
                .($blockingSales ? "، منها {$blockingSales} يؤخر طلبات بيع معلّقة" : '').'. تابع المورد أو ألغِ الأمر.',
            'warehouse',
            ['route' => '/admin/purchases/orders', 'count' => $overduePoCount, 'blocking_sales_orders' => $blockingSales]
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
