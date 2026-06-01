<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\Payment;
use App\Models\PurchaseReceipt;
use App\Models\Payroll;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index()
    {
        $salesRevenue = Invoice::where('status', Invoice::STATUS_PAID)->sum('total');
        $openInvoices = Invoice::where('status', Invoice::STATUS_PENDING)->count();
        $purchaseOrders = PurchaseOrder::count();
        $employeeCount = Employee::count();
        $openTickets = Ticket::where('status', 'open')->count();

        return view('admin.reports.index', compact(
            'salesRevenue',
            'openInvoices',
            'purchaseOrders',
            'employeeCount',
            'openTickets'
        ));
    }

    public function salesReport(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfDay());

        $quotes = Quote::whereBetween('created_at', [$startDate, $endDate])->get();
        $salesOrders = SalesOrder::whereBetween('created_at', [$startDate, $endDate])->get();
        $invoices = Invoice::whereBetween('created_at', [$startDate, $endDate])->get();
        $payments = Payment::whereBetween('created_at', [$startDate, $endDate])->get();

        // Chart data
        $dailySales = Invoice::selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $salesByStatus = SalesOrder::selectRaw('status, COUNT(*) as count, SUM(total) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status')
            ->get();

        $topCustomers = Customer::select('customers.*', \DB::raw('SUM(invoices.total) as total_sales'))
            ->join('invoices', 'customers.id', '=', 'invoices.customer_id')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->groupBy('customers.id')
            ->orderBy('total_sales', 'desc')
            ->limit(10)
            ->get();

        $topProducts = Product::select('products.*', \DB::raw('SUM(sales_order_items.quantity) as total_sold'))
            ->join('sales_order_items', 'products.id', '=', 'sales_order_items.product_id')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->whereBetween('sales_orders.created_at', [$startDate, $endDate])
            ->groupBy('products.id')
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->get();

        return view('admin.reports.sales', compact(
            'quotes', 'salesOrders', 'invoices', 'payments',
            'dailySales', 'salesByStatus', 'topCustomers', 'topProducts',
            'startDate', 'endDate'
        ));
    }

    public function inventoryReport(Request $request)
    {
        $products = Product::where('is_active', true)->with('category')->get();
        
        $lowStockProducts = Product::where('stock_quantity', '<=', 10)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();

        $outOfStockProducts = Product::where('stock_quantity', '<=', 0)
            ->where('is_active', true)
            ->with('category')
            ->get();

        $totalStockValue = Product::where('is_active', true)->sum(\DB::raw('stock_quantity * price'));

        $stockByCategory = Product::selectRaw('category_id, SUM(stock_quantity) as total, SUM(stock_quantity * price) as value')
            ->where('is_active', true)
            ->with('category')
            ->groupBy('category_id')
            ->get();

        $purchaseReceipts = PurchaseReceipt::latest()->limit(20)->get();

        return view('admin.reports.inventory', compact(
            'products', 'lowStockProducts', 'outOfStockProducts',
            'totalStockValue', 'stockByCategory', 'purchaseReceipts'
        ));
    }

    public function financialReport(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfDay());

        $invoices = Invoice::whereBetween('created_at', [$startDate, $endDate])->get();
        $payments = Payment::whereBetween('created_at', [$startDate, $endDate])->get();

        $totalRevenue = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', Invoice::STATUS_PAID)
            ->sum('total');

        $pendingRevenue = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', Invoice::STATUS_PENDING)
            ->sum('total');

        $totalPayments = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');

        $customerBalances = Customer::all();
        $supplierBalances = Supplier::all();

        // Chart data
        $monthlyRevenue = Invoice::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total) as total')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $paymentsByMethod = Payment::selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('payment_method')
            ->get();

        return view('admin.reports.financial', compact(
            'invoices', 'payments', 'totalRevenue', 'pendingRevenue',
            'totalPayments', 'customerBalances', 'supplierBalances',
            'monthlyRevenue', 'paymentsByMethod', 'startDate', 'endDate'
        ));
    }

    public function payrollReport(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfDay());

        $payrolls = Payroll::whereBetween('created_at', [$startDate, $endDate])
            ->with('employee')
            ->get();

        $totalPayroll = $payrolls->sum('net_salary');
        $totalBasicSalary = $payrolls->sum('basic_salary');
        $totalOvertime = $payrolls->sum(\DB::raw('overtime_hours * overtime_rate'));
        $totalBonuses = $payrolls->sum('bonuses');
        $totalDeductions = $payrolls->sum('deductions');

        $payrollByStatus = Payroll::selectRaw('status, COUNT(*) as count, SUM(net_salary) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status')
            ->get();

        $monthlyPayroll = Payroll::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(net_salary) as total')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('admin.reports.payroll', compact(
            'payrolls', 'totalPayroll', 'totalBasicSalary',
            'totalOvertime', 'totalBonuses', 'totalDeductions',
            'payrollByStatus', 'monthlyPayroll', 'startDate', 'endDate'
        ));
    }

    public function export()
    {
        return view('admin.reports.export');
    }
}
