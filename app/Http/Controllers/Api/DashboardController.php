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
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\Payroll;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        try {
            $today = today();
            $weekStart = now()->startOfWeek();
            $monthStart = now()->startOfMonth();

            $invoiceRevenue = [
                'today' => (float) Invoice::whereDate('created_at', $today)->sum('total'),
                'week' => (float) Invoice::whereDate('created_at', '>=', $weekStart)->sum('total'),
                'month' => (float) Invoice::whereDate('created_at', '>=', $monthStart)->sum('total'),
                'total' => (float) Invoice::sum('total'),
            ];

            $invoiceCounts = [
                'total' => Invoice::count(),
                'paid' => Invoice::where('status', Invoice::STATUS_PAID)->count(),
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

            return response()->json([
                'success' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'products' => $productCounts,
                    'categories' => ['total' => Category::count()],
                    'customers' => $customerCounts,
                    'inquiries' => $inquiryCounts,
                    'invoices' => array_merge(['revenue' => $invoiceRevenue], $invoiceCounts),
                    'payments' => array_merge(['amounts' => $paymentAmounts], $paymentCounts),
                    'quotes' => $quoteCounts,
                    'sales_orders' => $salesOrderCounts,
                    'production' => $productionCounts,
                    'payrolls' => $payrollCounts,
                    'purchase_receipts' => $purchaseReceiptCounts,
                    'recent_invoices' => $recentInvoices,
                    'top_products' => $topProducts,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
