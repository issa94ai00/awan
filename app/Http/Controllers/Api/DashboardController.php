<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\Payroll;
use App\Models\Inquiry;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\CycleCount;
use App\Models\PickingList;
use App\Models\PackingList;
use App\Models\RmaRequest;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\Dashboard as AnalyticsDashboard;
use App\Models\AnalyticsMetric;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        try {
            \Log::info('DashboardController::stats - Starting dashboard statistics retrieval');
            
            $today = today();
            $weekStart = now()->startOfWeek();
            $monthStart = now()->startOfMonth();

            \Log::info('DashboardController::stats - Fetching invoice revenue data');
            
            // Revenue calculation based on status (confirmed, processing, shipped, delivered)
            $revenueStatuses = [Invoice::STATUS_CONFIRMED, Invoice::STATUS_PROCESSING, Invoice::STATUS_SHIPPED, Invoice::STATUS_DELIVERED];
            
            $invoiceRevenue = [
                'today' => (float) Invoice::whereDate('created_at', $today)
                    ->whereIn('status', $revenueStatuses)
                    ->sum('total'),
                'week' => (float) Invoice::whereDate('created_at', '>=', $weekStart)
                    ->whereIn('status', $revenueStatuses)
                    ->sum('total'),
                'month' => (float) Invoice::whereDate('created_at', '>=', $monthStart)
                    ->whereIn('status', $revenueStatuses)
                    ->sum('total'),
                'total' => (float) Invoice::whereIn('status', $revenueStatuses)->sum('total'),
            ];

            // Total sales (all statuses except cancelled)
            $totalSales = [
                'today' => (float) Invoice::whereDate('created_at', $today)
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->sum('total'),
                'week' => (float) Invoice::whereDate('created_at', '>=', $weekStart)
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->sum('total'),
                'month' => (float) Invoice::whereDate('created_at', '>=', $monthStart)
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->sum('total'),
                'total' => (float) Invoice::where('status', '!=', Invoice::STATUS_CANCELLED)->sum('total'),
            ];

            $invoiceCounts = [
                'total' => Invoice::count(),
                'delivered' => Invoice::where('status', Invoice::STATUS_DELIVERED)->count(),
                'pending' => Invoice::where('status', Invoice::STATUS_PENDING)->count(),
                'cancelled' => Invoice::where('status', Invoice::STATUS_CANCELLED)->count(),
            ];

            $paymentAmounts = [
                'completed' => (float) Payment::where('status', Payment::STATUS_COMPLETED)->sum('amount'),
                'pending' => (float) Payment::where('status', Payment::STATUS_PENDING)->sum('amount'),
                'refunded' => (float) Payment::where('status', Payment::STATUS_REFUNDED)->sum('amount'),
            ];

            $paymentCounts = [
                'total' => Payment::count(),
                'completed' => Payment::where('status', Payment::STATUS_COMPLETED)->count(),
                'pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
                'refunded' => Payment::where('status', Payment::STATUS_REFUNDED)->count(),
            ];

            $productCounts = [
                'total' => Product::count(),
                'active' => Product::where('is_active', 1)->count(),
                'featured' => Product::where('is_featured', 1)->count(),
                'in_stock' => Product::where('in_stock', 1)->count(),
                'low_stock' => Product::whereColumn('stock_quantity', '<=', 'min_stock')->count(),
            ];

            $erpStats = [
                'total_revenue' => (float) Invoice::whereIn('status', $revenueStatuses)->sum('total'),
                'total_sales' => (float) Invoice::where('status', '!=', Invoice::STATUS_CANCELLED)->sum('total'),
                'total_expenses' => (float) \App\Models\PurchaseReceiptItem::sum('total'),
                'active_customers' => Customer::count(),
                'pending_invoices' => Invoice::where('status', Invoice::STATUS_PENDING)->count(),
                'monthly_revenue' => (float) Invoice::whereDate('created_at', '>=', $monthStart)
                    ->whereIn('status', $revenueStatuses)
                    ->sum('total'),
                'monthly_sales' => (float) Invoice::whereDate('created_at', '>=', $monthStart)
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->sum('total'),
            ];

            $quoteCounts = [
                'total' => Quote::count(),
                'draft' => Quote::where('status', Quote::STATUS_DRAFT)->count(),
                'sent' => Quote::where('status', Quote::STATUS_SENT)->count(),
                'accepted' => Quote::where('status', Quote::STATUS_ACCEPTED)->count(),
                'rejected' => Quote::where('status', Quote::STATUS_REJECTED)->count(),
                'expired' => Quote::where('status', Quote::STATUS_EXPIRED)->count(),
            ];

            $salesOrderCounts = [
                'total' => SalesOrder::count(),
                'pending' => SalesOrder::where('status', SalesOrder::STATUS_PENDING)->count(),
                'processing' => SalesOrder::where('status', SalesOrder::STATUS_PROCESSING)->count(),
                'shipped' => SalesOrder::where('status', SalesOrder::STATUS_SHIPPED)->count(),
                'delivered' => SalesOrder::where('status', SalesOrder::STATUS_DELIVERED)->count(),
                'cancelled' => SalesOrder::where('status', SalesOrder::STATUS_CANCELLED)->count(),
            ];

            $productionCounts = [
                'total' => ProductionOrder::count(),
                'pending' => ProductionOrder::where('status', ProductionOrder::STATUS_PENDING)->count(),
                'in_progress' => ProductionOrder::where('status', ProductionOrder::STATUS_IN_PROGRESS)->count(),
                'completed' => ProductionOrder::where('status', ProductionOrder::STATUS_COMPLETED)->count(),
                'cancelled' => ProductionOrder::where('status', ProductionOrder::STATUS_CANCELLED)->count(),
            ];

            $payrollCounts = [
                'total' => Payroll::count(),
                'pending' => Payroll::where('status', Payroll::STATUS_PENDING)->count(),
                'processed' => Payroll::where('status', Payroll::STATUS_PROCESSED)->count(),
                'paid' => Payroll::where('status', Payroll::STATUS_PAID)->count(),
            ];

            $purchaseReceiptCounts = [
                'total' => PurchaseReceipt::count(),
            ];

            $customerCounts = [
                'total' => Customer::count(),
            ];

            $inquiryCounts = [
                'total' => Inquiry::count(),
                'new' => Inquiry::where('status', Inquiry::STATUS_NEW)->count(),
                'read' => Inquiry::where('status', Inquiry::STATUS_READ)->count(),
                'replied' => Inquiry::where('status', Inquiry::STATUS_REPLIED)->count(),
            ];

            $recentInvoices = Invoice::with('customer')
                ->orderByDesc('created_at')
                ->take(5)
                ->get(['id', 'invoice_number', 'customer_id', 'total', 'status', 'created_at'])
                ->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'customer_name' => $invoice->customer?->name ?? 'عميل',
                        'total' => (float) $invoice->total,
                        'status' => $invoice->status,
                        'created_at' => $invoice->created_at?->toDateString(),
                    ];
                });

            $topProducts = Product::where('is_active', 1)
                ->orderByDesc('stock_quantity')
                ->take(4)
                ->get(['id', 'name_ar', 'name_en', 'price', 'stock_quantity', 'image_main'])
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name_ar' => $product->name_ar,
                        'name_en' => $product->name_en,
                        'price' => (float) $product->price,
                        'stock_quantity' => $product->stock_quantity,
                        'image' => $product->image_main ? asset('storage/' . $product->image_main) : null,
                    ];
                });

            $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'min_stock')
                ->where('is_active', 1)
                ->orderBy('stock_quantity')
                ->take(10)
                ->get(['id', 'name_ar', 'name_en', 'sku', 'stock_quantity', 'min_stock'])
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name_ar' => $product->name_ar,
                        'name_en' => $product->name_en,
                        'sku' => $product->sku,
                        'stock_quantity' => $product->stock_quantity,
                        'min_stock' => $product->min_stock,
                    ];
                });

            $wmsStats = [
                'warehouses_count' => Warehouse::count(),
                'bins_count' => WarehouseBin::count(),
                'picking_pending' => PickingList::where('status', PickingList::STATUS_PENDING)->count(),
                'picking_in_progress' => PickingList::where('status', PickingList::STATUS_IN_PROGRESS)->count(),
                'picking_completed' => PickingList::where('status', PickingList::STATUS_COMPLETED)->count(),
                'packing_pending' => PackingList::where('status', PackingList::STATUS_PENDING)->count(),
                'packing_in_progress' => PackingList::where('status', PackingList::STATUS_IN_PROGRESS)->count(),
                'packing_completed' => PackingList::where('status', PackingList::STATUS_COMPLETED)->count(),
                'cycle_counts_count' => CycleCount::count(),
                'cycle_counts_completed' => CycleCount::where('status', CycleCount::STATUS_COMPLETED)->count(),
            ];

            $rmaStats = [
                'total' => RmaRequest::count(),
                'pending' => RmaRequest::where('status', RmaRequest::STATUS_PENDING)->count(),
                'approved' => RmaRequest::where('status', RmaRequest::STATUS_APPROVED)->count(),
                'rejected' => RmaRequest::where('status', RmaRequest::STATUS_REJECTED)->count(),
                'completed' => RmaRequest::where('status', RmaRequest::STATUS_COMPLETED)->count(),
                'refunded_amount' => (float) RmaRequest::where('status', RmaRequest::STATUS_COMPLETED)->sum('refund_amount'),
            ];

            $workflowStats = [
                'total' => Workflow::count(),
                'active' => Workflow::where('status', Workflow::STATUS_ACTIVE)->count(),
                'inactive' => Workflow::where('status', Workflow::STATUS_INACTIVE)->count(),
                'executions_total' => WorkflowExecution::count(),
                'executions_completed' => WorkflowExecution::where('status', WorkflowExecution::STATUS_COMPLETED)->count(),
                'executions_failed' => WorkflowExecution::where('status', WorkflowExecution::STATUS_FAILED)->count(),
            ];

            $notificationStats = [
                'total' => Notification::count(),
                'templates' => NotificationTemplate::count(),
            ];

            $auditStats = [
                'total' => AuditLog::count(),
                'today' => AuditLog::whereDate('created_at', today())->count(),
            ];

            $recentAuditLogs = AuditLog::with('user')
                ->orderByDesc('created_at')
                ->take(5)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'user_name' => $log->user?->name ?? 'نظام',
                        'action' => $log->action,
                        'action_text' => $log->action_text,
                        'module' => $log->module,
                        'module_text' => $log->module_text,
                        'description' => $log->description,
                        'created_at' => $log->created_at?->toDateTimeString(),
                    ];
                });

            $analyticsStats = [
                'dashboards' => AnalyticsDashboard::count(),
                'reports' => Report::count(),
                'metrics' => AnalyticsMetric::count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'products' => $productCounts,
                    'erp' => $erpStats,
                    'categories' => ['total' => Category::count()],
                    'customers' => $customerCounts,
                    'inquiries' => $inquiryCounts,
                    'invoices' => array_merge([
                        'revenue' => $invoiceRevenue,
                        'total_sales' => $totalSales,
                    ], $invoiceCounts),
                    'payments' => array_merge(['amounts' => $paymentAmounts], $paymentCounts),
                    'quotes' => $quoteCounts,
                    'sales_orders' => $salesOrderCounts,
                    'production' => $productionCounts,
                    'payrolls' => $payrollCounts,
                    'purchase_receipts' => $purchaseReceiptCounts,
                    'recent_invoices' => $recentInvoices,
                    'top_products' => $topProducts,
                    'low_stock_products' => $lowStockProducts,
                    'wms' => $wmsStats,
                    'rma' => $rmaStats,
                    'workflows' => $workflowStats,
                    'notifications' => $notificationStats,
                    'audit' => $auditStats,
                    'recent_audit_logs' => $recentAuditLogs,
                    'analytics' => $analyticsStats,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('DashboardController::stats - Error retrieving dashboard statistics', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage(),
                'debug_file' => $e->getFile(),
                'debug_line' => $e->getLine(),
            ], 500);
        }
    }
}
