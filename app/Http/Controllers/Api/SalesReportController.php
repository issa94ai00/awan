<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\Employee;
use App\Models\WarehouseInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    public function salesReport(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'per_page' => 'nullable|integer|min:1|max:500',
            'group_by' => 'nullable|in:day,week,month,employee,customer,warehouse,status',
        ]);

        $query = SalesOrder::with(['customer', 'assignedEmployee', 'items.product']);
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->input('per_page', 20) ?: 20, 500);
        $salesOrders = $query->latest('order_date')->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Sales report retrieved successfully',
            'data' => [
                'sales_orders' => $salesOrders->items(),
                'summary' => $this->calculateSummary($query->clone()),
                'pagination' => [
                    'current_page' => $salesOrders->currentPage(),
                    'last_page' => $salesOrders->lastPage(),
                    'per_page' => $salesOrders->perPage(),
                    'total' => $salesOrders->total(),
                    'has_more_pages' => $salesOrders->hasMorePages(),
                ],
            ],
        ]);
    }

    public function salesSummary(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'group_by' => 'nullable|in:day,week,month,employee,customer,warehouse,status',
            'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $query = SalesOrder::query();
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $groupBy = $request->input('group_by', 'day');

        $data = match ($groupBy) {
            'employee' => $this->groupByEmployee($query),
            'customer' => $this->groupByCustomer($query),
            'warehouse' => $this->groupByWarehouse($query),
            'status' => $this->groupByStatus($query),
            'day' => $this->groupByDay($query),
            'week' => $this->groupByWeek($query),
            'month' => $this->groupByMonth($query),
            default => $this->groupByDay($query),
        };

        return response()->json([
            'success' => true,
            'message' => 'Sales summary retrieved successfully',
            'data' => [
                'group_by' => $groupBy,
                'summary' => $data,
                'overall' => $this->calculateSummary($query),
            ],
        ]);
    }

    public function salesDimensions(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $query = SalesOrder::query();
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sales dimensions retrieved successfully',
            'data' => [
                'employee_summary' => $this->groupByEmployee($query->clone()),
                'customer_summary' => $this->groupByCustomer($query->clone()),
                'warehouse_summary' => $this->groupByWarehouse($query->clone()),
                'overall' => $this->calculateSummary($query->clone()),
            ],
        ]);
    }

    public function salesPerformance(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $query = SalesOrder::query()->with(['items.product', 'customer', 'assignedEmployee', 'fulfillmentWarehouse']);
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->get();
        $summary = [
            'total_revenue' => (float) $orders->sum('total'),
            'total_cost' => (float) $orders->reduce(function ($carry, $order) {
                return $carry + $order->items->reduce(function ($sum, $item) {
                    $cost = (float) ($item->product?->cost_price ?? 0);
                    return $sum + ($cost * (float) ($item->quantity ?? 0));
                }, 0);
            }, 0),
            'gross_profit' => 0,
            'gross_margin' => 0,
            'total_orders' => $orders->count(),
        ];

        $summary['gross_profit'] = $summary['total_revenue'] - $summary['total_cost'];
        $summary['gross_margin'] = $summary['total_revenue'] > 0 ? round(($summary['gross_profit'] / $summary['total_revenue']) * 100, 2) : 0;

        $employeeSummary = $orders->groupBy('assigned_employee_id')->map(function ($group, $employeeId) {
            $totalRevenue = (float) $group->sum('total');
            $totalCost = (float) $group->reduce(function ($carry, $order) {
                return $carry + $order->items->reduce(function ($sum, $item) {
                    $cost = (float) ($item->product?->cost_price ?? 0);
                    return $sum + ($cost * (float) ($item->quantity ?? 0));
                }, 0);
            }, 0);
            $grossProfit = $totalRevenue - $totalCost;

            return [
                'employee_id' => (int) $employeeId,
                'employee_name' => $group->first()->assignedEmployee?->name ?? 'Unknown',
                'total_orders' => $group->count(),
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'gross_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
            ];
        })->values();

        $customerSummary = $orders->groupBy('customer_id')->map(function ($group, $customerId) {
            $totalRevenue = (float) $group->sum('total');
            $totalCost = (float) $group->reduce(function ($carry, $order) {
                return $carry + $order->items->reduce(function ($sum, $item) {
                    $cost = (float) ($item->product?->cost_price ?? 0);
                    return $sum + ($cost * (float) ($item->quantity ?? 0));
                }, 0);
            }, 0);
            $grossProfit = $totalRevenue - $totalCost;

            return [
                'customer_id' => (int) $customerId,
                'customer_name' => $group->first()->customer?->name ?? 'Unknown',
                'total_orders' => $group->count(),
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'gross_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
            ];
        })->values();

        $warehouseSummary = $orders->groupBy('fulfillment_warehouse_id')->map(function ($group, $warehouseId) {
            $totalRevenue = (float) $group->sum('total');
            $totalCost = (float) $group->reduce(function ($carry, $order) {
                return $carry + $order->items->reduce(function ($sum, $item) {
                    $cost = (float) ($item->product?->cost_price ?? 0);
                    return $sum + ($cost * (float) ($item->quantity ?? 0));
                }, 0);
            }, 0);
            $grossProfit = $totalRevenue - $totalCost;

            return [
                'warehouse_id' => (int) $warehouseId,
                'warehouse_name' => $group->first()->fulfillmentWarehouse?->name ?? 'Unknown',
                'total_orders' => $group->count(),
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'gross_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Sales performance retrieved successfully',
            'data' => [
                'summary' => $summary,
                'employee_summary' => $employeeSummary,
                'customer_summary' => $customerSummary,
                'warehouse_summary' => $warehouseSummary,
            ],
        ]);
    }

    public function productProfitability(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'product_id' => 'nullable|exists:products,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $query = SalesOrder::query()->with(['items.product', 'fulfillmentWarehouse']);
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->get();

        $productSummary = $orders->flatMap(function ($order) {
            return $order->items->map(function ($item) use ($order) {
                $product = $item->product;
                $revenue = (float) ($item->unit_price * $item->quantity);
                $cost = (float) (($product?->cost_price ?? 0) * ($item->quantity ?? 0));
                $grossProfit = $revenue - $cost;

                return [
                    'product_id' => (int) ($product?->id ?? 0),
                    'product_name' => $product?->name ?? 'Unknown',
                    'warehouse_id' => (int) ($order->fulfillment_warehouse_id ?? 0),
                    'warehouse_name' => $order->fulfillmentWarehouse?->name ?? 'Unknown',
                    'quantity' => (float) ($item->quantity ?? 0),
                    'total_revenue' => $revenue,
                    'total_cost' => $cost,
                    'gross_profit' => $grossProfit,
                    'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
                ];
            });
        })->filter(fn ($row) => (int) ($row['product_id'] ?? 0) > 0);

        $grouped = $productSummary->groupBy(fn ($row) => ($row['product_id'].'-'.$row['warehouse_id']));

        $finalProductSummary = $grouped->map(function ($rows) {
            $revenue = $rows->sum('total_revenue');
            $cost = $rows->sum('total_cost');
            $profit = $revenue - $cost;

            return [
                'product_id' => $rows->first()['product_id'],
                'product_name' => $rows->first()['product_name'],
                'warehouse_id' => $rows->first()['warehouse_id'],
                'warehouse_name' => $rows->first()['warehouse_name'],
                'quantity' => $rows->sum('quantity'),
                'total_revenue' => $revenue,
                'total_cost' => $cost,
                'gross_profit' => $profit,
                'gross_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
            ];
        })->values()->sortByDesc('gross_profit')->values();

        $totalRevenue = (float) $finalProductSummary->sum('total_revenue');
        $totalCost = (float) $finalProductSummary->sum('total_cost');
        $grossProfit = $totalRevenue - $totalCost;

        $summary = [
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'gross_profit' => (float) $grossProfit,
            'gross_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
            'product_count' => $finalProductSummary->count(),
            'top_product' => $finalProductSummary->first() ?: null,
            'lowest_product' => $finalProductSummary->last() ?: null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Product profitability retrieved successfully',
            'data' => [
                'summary' => $summary,
                'product_summary' => $finalProductSummary,
            ],
        ]);
    }

    public function inventoryDimensions(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'product_id' => 'nullable|exists:products,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
        ]);

        $query = WarehouseInventory::query()
            ->with(['product', 'warehouse'])
            ->select('warehouse_inventory.*')
            ->leftJoin('products', 'products.id', '=', 'warehouse_inventory.product_id');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_inventory.warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('product_id')) {
            $query->where('warehouse_inventory.product_id', $request->product_id);
        }

        $this->applyInventoryDateFilters($query, $request);

        $warehouseSummary = $query->clone()
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_inventory.warehouse_id')
            ->selectRaw('warehouse_inventory.warehouse_id as warehouse_id')
            ->selectRaw('warehouses.name as warehouse_name')
            ->selectRaw('SUM(warehouse_inventory.quantity) as total_quantity')
            ->selectRaw('SUM(warehouse_inventory.available_quantity) as total_available')
            ->selectRaw('SUM(warehouse_inventory.quantity * COALESCE(products.price, 0)) as total_value')
            ->groupBy('warehouse_inventory.warehouse_id', 'warehouses.name')
            ->get();

        $overallValue = (float) $query->clone()
            ->selectRaw('SUM(warehouse_inventory.quantity * COALESCE(products.price, 0)) as total_value')
            ->value('total_value');

        $overall = [
            'total_quantity' => (float) $query->clone()->sum('warehouse_inventory.quantity'),
            'total_available' => (float) $query->clone()->sum('warehouse_inventory.available_quantity'),
            'total_value' => $overallValue,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Inventory dimensions retrieved successfully',
            'data' => [
                'warehouse_summary' => $warehouseSummary->map(function ($item) {
                    return [
                        'warehouse_id' => $item->warehouse_id,
                        'warehouse_name' => $item->warehouse_name,
                        'total_quantity' => (float) ($item->total_quantity ?? 0),
                        'total_available' => (float) ($item->total_available ?? 0),
                        'total_value' => (float) ($item->total_value ?? 0),
                    ];
                }),
                'overall' => $overall,
            ],
        ]);
    }

    public function invoiceDimensions(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $query = Invoice::query();
        $this->applyInvoiceDateFilters($query, $request);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $customerSummary = $query
            ->select('customer_id')
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('SUM(total) as total_invoiced')
            ->groupBy('customer_id')
            ->get()
            ->map(function ($item) {
                $customer = \App\Models\Customer::find($item->customer_id);

                return [
                    'customer_id' => $item->customer_id,
                    'customer_name' => $customer ? $customer->name : 'Unknown',
                    'total_invoices' => (int) ($item->total_invoices ?? 0),
                    'total_invoiced' => (float) ($item->total_invoiced ?? 0),
                ];
            });

        $warehouseSummary = $query
            ->select('warehouse_id')
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('SUM(total) as total_invoiced')
            ->groupBy('warehouse_id')
            ->get()
            ->map(function ($item) {
                $warehouse = \App\Models\Warehouse::find($item->warehouse_id);

                return [
                    'warehouse_id' => $item->warehouse_id,
                    'warehouse_name' => $warehouse ? $warehouse->name : 'Unknown',
                    'total_invoices' => (int) ($item->total_invoices ?? 0),
                    'total_invoiced' => (float) ($item->total_invoiced ?? 0),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Invoice dimensions retrieved successfully',
            'data' => [
                'customer_summary' => $customerSummary,
                'warehouse_summary' => $warehouseSummary,
                'overall' => [
                    'total_invoices' => (int) $query->count(),
                    'total_invoiced' => (float) $query->sum('total'),
                    'paid_amount' => (float) $query->sum('paid_amount'),
                    'due_amount' => (float) $query->sum('due_amount'),
                ],
            ],
        ]);
    }

    public function topPerformers(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = SalesOrder::query()->whereNotNull('assigned_employee_id');
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        $limit = min((int) $request->input('limit', 10) ?: 10, 50);

        $topEmployees = $query
            ->select('assigned_employee_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('assigned_employee_id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $employee = Employee::find($item->assigned_employee_id);

                return [
                    'employee_id' => $item->assigned_employee_id,
                    'employee_name' => $employee ? $employee->name : 'Unknown',
                    'total_orders' => (int) ($item->total_orders ?? 0),
                    'total_sales' => (float) ($item->total_sales ?? 0),
                    'total_subtotal' => (float) ($item->total_subtotal ?? 0),
                    'average_order_value' => (float) ($item->average_order_value ?? 0),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Top performers retrieved successfully',
            'data' => $topEmployees,
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $query = SalesOrder::query()->with(['customer', 'assignedEmployee']);
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->latest('order_date')->get();
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['Order #', 'Date', 'Customer', 'Employee', 'Status', 'Subtotal', 'Discount', 'Tax', 'Total']);

        foreach ($rows as $row) {
            fputcsv($csv, [
                $row->order_number,
                $row->order_date?->format('Y-m-d') ?? '-',
                $row->customer?->name ?? '-',
                $row->assignedEmployee?->name ?? '-',
                $row->status,
                (float) $row->subtotal,
                (float) $row->discount,
                (float) $row->tax,
                (float) $row->total,
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sales-report.csv"',
        ]);
    }

    public function inventoryExport(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'product_id' => 'nullable|exists:products,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
        ]);

        $query = WarehouseInventory::query()->with(['product', 'warehouse']);
        $this->applyInventoryDateFilters($query, $request);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $rows = $query->get();
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['warehouse_name', 'product_name', 'sku', 'quantity', 'reserved_quantity', 'available_quantity', 'reorder_point', 'updated_at']);

        foreach ($rows as $row) {
            fputcsv($csv, [
                $row->warehouse?->name ?? '-',
                $row->product?->name_ar ?? $row->product?->name_en ?? '-',
                $row->product?->sku ?? '-',
                (float) ($row->quantity ?? 0),
                (float) ($row->reserved_quantity ?? 0),
                (float) ($row->available_quantity ?? 0),
                (float) ($row->reorder_point ?? 0),
                $row->updated_at?->format('Y-m-d H:i:s') ?? '-',
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="inventory-report.csv"',
        ]);
    }

    public function invoiceExport(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $query = Invoice::query()->with(['customer', 'warehouse']);
        $this->applyInvoiceDateFilters($query, $request);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->latest('created_at')->get();
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['invoice_number', 'customer_name', 'warehouse_name', 'created_at', 'status', 'subtotal', 'tax', 'discount', 'total', 'paid_amount', 'due_amount']);

        foreach ($rows as $row) {
            fputcsv($csv, [
                $row->invoice_number,
                $row->customer?->name ?? '-',
                $row->warehouse?->name ?? '-',
                $row->created_at?->format('Y-m-d H:i:s') ?? '-',
                $row->status,
                (float) ($row->subtotal ?? 0),
                (float) ($row->tax ?? 0),
                (float) ($row->discount ?? 0),
                (float) ($row->total ?? 0),
                (float) ($row->paid_amount ?? 0),
                (float) ($row->due_amount ?? 0),
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="invoice-report.csv"',
        ]);
    }

    private function applyDateFilters($query, Request $request): void
    {
        $type = $request->input('date_filter_type', 'all');

        if ($request->filled('date')) {
            $query->whereDate('order_date', $request->date);

            return;
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('order_date', [$request->start_date, $request->end_date]);

            return;
        }

        if ($request->filled('start_date')) {
            $query->whereDate('order_date', '>=', $request->start_date);

            return;
        }

        if ($request->filled('end_date')) {
            $query->whereDate('order_date', '<=', $request->end_date);

            return;
        }

        if ($type === 'today') {
            $query->whereDate('order_date', today());

            return;
        }

        if ($type === 'yesterday') {
            $query->whereDate('order_date', now()->subDay()->toDateString());

            return;
        }

        if ($type === 'this_week') {
            $query->whereBetween('order_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]);

            return;
        }

        if ($type === 'this_month') {
            $query->whereBetween('order_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);

            return;
        }

        if ($type === 'last_month') {
            $query->whereBetween('order_date', [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()]);
        }
    }

    private function applyInventoryDateFilters($query, Request $request): void
    {
        $type = $request->input('date_filter_type', 'all');

        if ($request->filled('date')) {
            $query->whereDate('updated_at', $request->date);

            return;
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('updated_at', [$request->start_date, $request->end_date]);

            return;
        }

        if ($type === 'today') {
            $query->whereDate('updated_at', today());

            return;
        }

        if ($type === 'yesterday') {
            $query->whereDate('updated_at', now()->subDay()->toDateString());

            return;
        }

        if ($type === 'this_week') {
            $query->whereBetween('updated_at', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]);

            return;
        }

        if ($type === 'this_month') {
            $query->whereBetween('updated_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);

            return;
        }

        if ($type === 'last_month') {
            $query->whereBetween('updated_at', [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()]);
        }
    }

    private function applyInvoiceDateFilters($query, Request $request): void
    {
        $type = $request->input('date_filter_type', 'all');

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);

            return;
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);

            return;
        }

        if ($type === 'today') {
            $query->whereDate('created_at', today());

            return;
        }

        if ($type === 'yesterday') {
            $query->whereDate('created_at', now()->subDay()->toDateString());

            return;
        }

        if ($type === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]);

            return;
        }

        if ($type === 'this_month') {
            $query->whereBetween('created_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);

            return;
        }

        if ($type === 'last_month') {
            $query->whereBetween('created_at', [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()]);
        }
    }

    private function calculateSummary($query)
    {
        return [
            'total_orders' => (int) $query->count(),
            'total_sales' => (float) $query->sum('total'),
            'total_subtotal' => (float) $query->sum('subtotal'),
            'total_discount' => (float) $query->sum('discount'),
            'total_tax' => (float) $query->sum('tax'),
            'total_shipping' => (float) $query->sum('shipping_cost'),
            'average_order_value' => $query->count() > 0 ? (float) $query->avg('total') : 0,
        ];
    }

    private function groupByEmployee($query)
    {
        return $query
            ->select('assigned_employee_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('assigned_employee_id')
            ->get()
            ->map(function ($item) {
                $employee = Employee::find($item->assigned_employee_id);
                return [
                    'employee_id' => $item->assigned_employee_id,
                    'employee_name' => $employee ? $employee->name : 'Unknown',
                    'total_orders' => (int) ($item->total_orders ?? 0),
                    'total_sales' => (float) ($item->total_sales ?? 0),
                    'total_subtotal' => (float) ($item->total_subtotal ?? 0),
                    'average_order_value' => (float) ($item->average_order_value ?? 0),
                ];
            });
    }

    private function groupByCustomer($query)
    {
        return $query
            ->select('customer_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('customer_id')
            ->get()
            ->map(function ($item) {
                $customer = \App\Models\Customer::find($item->customer_id);

                return [
                    'customer_id' => $item->customer_id,
                    'customer_name' => $customer ? $customer->name : 'Unknown',
                    'total_orders' => (int) ($item->total_orders ?? 0),
                    'total_sales' => (float) ($item->total_sales ?? 0),
                    'total_subtotal' => (float) ($item->total_subtotal ?? 0),
                    'average_order_value' => (float) ($item->average_order_value ?? 0),
                ];
            });
    }

    private function groupByWarehouse($query)
    {
        return $query
            ->select('fulfillment_warehouse_id as warehouse_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('fulfillment_warehouse_id')
            ->get()
            ->map(function ($item) {
                $warehouse = \App\Models\Warehouse::find($item->warehouse_id);

                return [
                    'warehouse_id' => $item->warehouse_id,
                    'warehouse_name' => $warehouse ? $warehouse->name : 'Unknown',
                    'total_orders' => (int) ($item->total_orders ?? 0),
                    'total_sales' => (float) ($item->total_sales ?? 0),
                    'total_subtotal' => (float) ($item->total_subtotal ?? 0),
                    'average_order_value' => (float) ($item->average_order_value ?? 0),
                ];
            });
    }

    private function groupByStatus($query)
    {
        return $query
            ->select('status')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'status_text' => $this->getStatusText($item->status),
                    'total_orders' => $item->total_orders,
                    'total_sales' => (float) $item->total_sales,
                    'total_subtotal' => (float) $item->total_subtotal,
                ];
            });
    }

    private function groupByDay($query)
    {
        return $query
            ->selectRaw('DATE(order_date) as date')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total_orders' => $item->total_orders,
                    'total_sales' => (float) $item->total_sales,
                    'total_subtotal' => (float) $item->total_subtotal,
                ];
            });
    }

    private function groupByWeek($query)
    {
        return $query
            ->selectRaw('YEAR(order_date) as year')
            ->selectRaw('WEEK(order_date) as week')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->groupBy('year', 'week')
            ->orderBy('year')
            ->orderBy('week')
            ->get()
            ->map(function ($item) {
                return [
                    'year' => $item->year,
                    'week' => $item->week,
                    'total_orders' => $item->total_orders,
                    'total_sales' => (float) $item->total_sales,
                    'total_subtotal' => (float) $item->total_subtotal,
                ];
            });
    }

    private function groupByMonth($query)
    {
        return $query
            ->selectRaw('YEAR(order_date) as year')
            ->selectRaw('MONTH(order_date) as month')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'year' => $item->year,
                    'month' => $item->month,
                    'total_orders' => $item->total_orders,
                    'total_sales' => (float) $item->total_sales,
                    'total_subtotal' => (float) $item->total_subtotal,
                ];
            });
    }

    private function getStatusText($status)
    {
        return match($status) {
            'pending' => 'معلق',
            'confirmed' => 'مؤكد',
            'processing' => 'قيد المعالجة',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم التسليم',
            'cancelled' => 'ملغي',
            default => $status,
        };
    }
}
