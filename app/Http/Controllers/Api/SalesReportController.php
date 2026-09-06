<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\Employee;
use App\Models\Warehouse;
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

        // invoiced_total/invoices_count ride on the same query as the listing so
        // each row can say how much of itself has actually been billed — an
        // order and its invoice are two different documents and can disagree.
        $query = SalesOrder::with(['customer', 'assignedEmployee', 'items.product', 'invoices:id,sales_order_id,invoice_number,status,total'])
            ->withSum('invoices as invoiced_total', 'total')
            ->withCount('invoices as invoices_count');
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('sales_orders.assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('sales_orders.customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('sales_orders.fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('sales_orders.status', $request->status);
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
            $query->where('sales_orders.assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('sales_orders.customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('sales_orders.fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('sales_orders.status', $request->status);
        }

        $groupBy = $request->input('group_by', 'day');

        // Each groupByX() adds its own select()/groupBy() to whatever builder
        // it is handed — Eloquent builder methods mutate in place, they don't
        // return a copy. Handing them $query directly left calculateSummary()
        // reusing that same, now-grouped builder for the "overall" totals: the
        // group's GROUP BY rode along into the join calculateSummary() runs for
        // invoiced_total, which is where "employee" and "customer" grouping
        // crashed outright (both sales_orders and invoices carry those
        // columns, so the leftover bare `assigned_employee_id`/`customer_id`
        // became ambiguous) — and the other groupings silently miscomputed
        // "overall" instead of crashing. A clone per call keeps $query itself
        // untouched for calculateSummary() below.
        $data = match ($groupBy) {
            'employee' => $this->groupByEmployee($query->clone()),
            'customer' => $this->groupByCustomer($query->clone()),
            'warehouse' => $this->groupByWarehouse($query->clone()),
            'status' => $this->groupByStatus($query->clone()),
            'day' => $this->groupByDay($query->clone()),
            'week' => $this->groupByWeek($query->clone()),
            'month' => $this->groupByMonth($query->clone()),
            default => $this->groupByDay($query->clone()),
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
            $query->where('sales_orders.assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('sales_orders.customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('sales_orders.fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('sales_orders.status', $request->status);
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

        $query = SalesOrder::query();
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('sales_orders.assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('sales_orders.customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('sales_orders.fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('sales_orders.status', $request->status);
        }

        // Revenue and count come straight off sales_orders — no join, no
        // fan-out. Cost is a separate grouped query through the items table;
        // joining items into this same query would multiply every order's
        // revenue by however many lines it has.
        $costQuery = fn () => (clone $query)
            ->join('sales_order_items', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->leftJoin('products', 'products.id', '=', 'sales_order_items.product_id');

        $totalRevenue = (float) (clone $query)->sum('total');
        $totalOrders = (int) (clone $query)->count();
        $totalCost = (float) $costQuery()->sum(DB::raw('sales_order_items.quantity * COALESCE(products.cost_price, 0)'));
        $grossProfit = $totalRevenue - $totalCost;

        $summary = [
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'gross_profit' => $grossProfit,
            'gross_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
            'total_orders' => $totalOrders,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Sales performance retrieved successfully',
            'data' => [
                'summary' => $summary,
                'employee_summary' => $this->salesPerformanceByGroup($query, $costQuery, 'assigned_employee_id', 'employee_id', 'employee_name', Employee::class),
                'customer_summary' => $this->salesPerformanceByGroup($query, $costQuery, 'customer_id', 'customer_id', 'customer_name', Customer::class),
                'warehouse_summary' => $this->salesPerformanceByGroup($query, $costQuery, 'fulfillment_warehouse_id', 'warehouse_id', 'warehouse_name', Warehouse::class),
            ],
        ]);
    }

    /**
     * One grouping's revenue/cost/margin breakdown for salesPerformance().
     * Revenue and order count are grouped directly on sales_orders; cost
     * reuses the same items join as the overall total, grouped the same way
     * and merged here by group id — so an order's revenue is never
     * multiplied by its item count, and every group's employee/customer/
     * warehouse name is one batched query instead of one per group.
     */
    private function salesPerformanceByGroup($query, callable $costQuery, string $column, string $idKey, string $nameKey, string $modelClass)
    {
        $revenueRows = (clone $query)
            ->select($column)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_revenue')
            ->groupBy($column)
            ->get()
            ->keyBy($column);

        $costByGroup = $costQuery()
            ->select('sales_orders.'.$column)
            ->selectRaw('SUM(sales_order_items.quantity * COALESCE(products.cost_price, 0)) as total_cost')
            ->groupBy('sales_orders.'.$column)
            ->pluck('total_cost', $column);

        $names = $this->namesFor($modelClass, $revenueRows->keys()->all());

        return $revenueRows->map(function ($row) use ($costByGroup, $names, $column, $idKey, $nameKey) {
            $groupId = $row->{$column};
            $totalRevenue = (float) ($row->total_revenue ?? 0);
            $totalCost = (float) ($costByGroup[$groupId] ?? 0);
            $grossProfit = $totalRevenue - $totalCost;

            return [
                $idKey => (int) $groupId,
                $nameKey => $names[$groupId] ?? 'غير معروف',
                'total_orders' => (int) ($row->total_orders ?? 0),
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'gross_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
            ];
        })->values();
    }

    /**
     * Batch-loads {id => name} for a set of ids in one query. Several report
     * endpoints turn a grouped SQL result (one row per employee/customer/
     * warehouse) into named rows; doing that with Model::find() inside the
     * map fired one extra query per distinct id in the result. This is one
     * query for the whole batch, however many groups there are.
     */
    private function namesFor(string $modelClass, iterable $ids): array
    {
        $ids = array_values(array_unique(array_filter(is_array($ids) ? $ids : iterator_to_array($ids))));

        if ($ids === []) {
            return [];
        }

        return $modelClass::whereIn('id', $ids)->get()->pluck('name', 'id')->all();
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

        $query = SalesOrder::query()->with(['items.product', 'items.allocations.warehouse', 'fulfillmentWarehouse']);
        $this->applyDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('sales_orders.assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('sales_orders.customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('sales_orders.fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('sales_orders.status', $request->status);
        }

        $orders = $query->get();

        $productSummary = $orders->flatMap(function ($order) {
            return $order->items->flatMap(function ($item) use ($order) {
                $product = $item->product;
                $unitRevenue = (float) $item->unit_price;
                $unitCost = (float) ($product?->cost_price ?? 0);

                // An item split across warehouses (see SalesOrderItem::allocations)
                // has its revenue and cost split the same way, so each
                // warehouse's row reflects only the share it actually
                // fulfilled — rather than crediting the whole line to
                // whichever warehouse happened to own the order.
                if ($item->allocations->isNotEmpty()) {
                    return $item->allocations->map(function ($allocation) use ($product, $unitRevenue, $unitCost) {
                        $quantity = (float) ($allocation->quantity ?? 0);
                        $revenue = $unitRevenue * $quantity;
                        $cost = $unitCost * $quantity;
                        $grossProfit = $revenue - $cost;

                        return [
                            'product_id' => (int) ($product?->id ?? 0),
                            'product_name' => $product?->name ?? 'غير معروف',
                            'warehouse_id' => (int) ($allocation->warehouse_id ?? 0),
                            'warehouse_name' => $allocation->warehouse?->name ?? 'غير معروف',
                            'quantity' => $quantity,
                            'total_revenue' => $revenue,
                            'total_cost' => $cost,
                            'gross_profit' => $grossProfit,
                            'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
                        ];
                    });
                }

                // No fulfilment plan recorded yet — fall back to the order's
                // own warehouse, the only thing known about it so far.
                $quantity = (float) ($item->quantity ?? 0);
                $revenue = $unitRevenue * $quantity;
                $cost = $unitCost * $quantity;
                $grossProfit = $revenue - $cost;

                return [[
                    'product_id' => (int) ($product?->id ?? 0),
                    'product_name' => $product?->name ?? 'غير معروف',
                    'warehouse_id' => (int) ($order->fulfillment_warehouse_id ?? 0),
                    'warehouse_name' => $order->fulfillmentWarehouse?->name ?? 'غير معروف',
                    'quantity' => $quantity,
                    'total_revenue' => $revenue,
                    'total_cost' => $cost,
                    'gross_profit' => $grossProfit,
                    'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
                ]];
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

        $customerRows = $query
            ->clone()
            ->select('customer_id')
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('SUM(total) as total_invoiced')
            ->selectRaw('SUM(paid_amount) as paid_amount')
            ->selectRaw('SUM(due_amount) as due_amount')
            ->groupBy('customer_id')
            ->get();
        $customerNames = $this->namesFor(Customer::class, $customerRows->pluck('customer_id')->all());
        $customerSummary = $customerRows->map(function ($item) use ($customerNames) {
            return [
                'customer_id' => $item->customer_id,
                'customer_name' => $customerNames[$item->customer_id] ?? 'غير معروف',
                'total_invoices' => (int) ($item->total_invoices ?? 0),
                'total_invoiced' => (float) ($item->total_invoiced ?? 0),
                'paid_amount' => (float) ($item->paid_amount ?? 0),
                'due_amount' => (float) ($item->due_amount ?? 0),
            ];
        });

        $warehouseRows = $query
            ->clone()
            ->select('warehouse_id')
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('SUM(total) as total_invoiced')
            ->selectRaw('SUM(paid_amount) as paid_amount')
            ->selectRaw('SUM(due_amount) as due_amount')
            ->groupBy('warehouse_id')
            ->get();
        $warehouseNames = $this->namesFor(Warehouse::class, $warehouseRows->pluck('warehouse_id')->all());
        $warehouseSummary = $warehouseRows->map(function ($item) use ($warehouseNames) {
            return [
                'warehouse_id' => $item->warehouse_id,
                'warehouse_name' => $warehouseNames[$item->warehouse_id] ?? 'غير معروف',
                'total_invoices' => (int) ($item->total_invoices ?? 0),
                'total_invoiced' => (float) ($item->total_invoiced ?? 0),
                'paid_amount' => (float) ($item->paid_amount ?? 0),
                'due_amount' => (float) ($item->due_amount ?? 0),
            ];
        });

        // Credited the same way sales-order performance is: nothing for a
        // counter sale nobody was assigned to, so it is left out rather than
        // lumped under a fake "Unknown" rep.
        $employeeRows = $query
            ->clone()
            ->whereNotNull('assigned_employee_id')
            ->select('assigned_employee_id')
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('SUM(total) as total_invoiced')
            ->selectRaw('SUM(paid_amount) as paid_amount')
            ->selectRaw('SUM(due_amount) as due_amount')
            ->groupBy('assigned_employee_id')
            ->get();
        $employeeNames = $this->namesFor(Employee::class, $employeeRows->pluck('assigned_employee_id')->all());
        $employeeSummary = $employeeRows->map(function ($item) use ($employeeNames) {
            return [
                'employee_id' => $item->assigned_employee_id,
                'employee_name' => $employeeNames[$item->assigned_employee_id] ?? 'غير معروف',
                'total_invoices' => (int) ($item->total_invoices ?? 0),
                'total_invoiced' => (float) ($item->total_invoiced ?? 0),
                'paid_amount' => (float) ($item->paid_amount ?? 0),
                'due_amount' => (float) ($item->due_amount ?? 0),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Invoice dimensions retrieved successfully',
            'data' => [
                'employee_summary' => $employeeSummary,
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

    /**
     * Invoice-side counterpart to salesReport(): the professional sales
     * screen otherwise reports a pipeline of sales orders that were never
     * necessarily billed. Same filters, same shape, so the two paginated
     * tables sit on the report as two views of one funnel rather than as
     * unrelated screens.
     */
    public function invoiceReport(Request $request)
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
            'per_page' => 'nullable|integer|min:1|max:500',
            'sort' => 'nullable|in:profit_asc,profit_desc,margin_asc,margin_desc',
        ]);

        // salesOrder is the reverse of salesReport()'s invoices relation — lets
        // the invoice list point back at the order it was billed against,
        // instead of the two documents only being joinable in a spreadsheet.
        $query = Invoice::with(['customer', 'assignedEmployee', 'warehouse', 'salesOrder:id,order_number']);
        $this->applyInvoiceDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Snapshotted before any sort join: the summary runs SUM() over this,
        // and a join to the line items would multiply every invoice's total by
        // its line count.
        $filtered = clone $query;

        $perPage = min((int) $request->input('per_page', 20) ?: 20, 500);
        $sort = $request->input('sort');

        // Ordering by profit is the whole point of reporting it per invoice:
        // the loss-makers are what the operator is looking for, and they are
        // never the newest rows. Needs the cost joined in, because profit is
        // not a column — it is revenue net of tax minus what the lines cost.
        if (in_array($sort, ['profit_asc', 'profit_desc', 'margin_asc', 'margin_desc'], true)) {
            $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
            $expression = str_starts_with($sort, 'margin')
                // Margin is undefined without revenue; those rows sort as zero
                // rather than dividing by it. The * 100.0 is not cosmetic: it
                // makes the numerator a real, and without it SQLite divides two
                // integers and truncates every margin between -100% and 100% to
                // zero — which is to say, orders the column by nothing at all.
                ? 'CASE WHEN (invoices.total - invoices.tax) > 0
                        THEN (((invoices.total - invoices.tax) - COALESCE(line_costs.total_cost, 0)) * 100.0)
                             / (invoices.total - invoices.tax)
                        ELSE 0 END'
                : '(invoices.total - invoices.tax) - COALESCE(line_costs.total_cost, 0)';

            $query->select('invoices.*')
                ->leftJoinSub($this->invoiceLineCostQuery(), 'line_costs', 'line_costs.invoice_id', '=', 'invoices.id')
                ->orderByRaw($expression.' '.$direction)
                ->orderBy('invoices.id', 'desc');
        } else {
            $query->latest('created_at')->latest('id');
        }

        $invoices = $query->paginate($perPage);

        $this->attachInvoiceProfitability($invoices->items());

        return response()->json([
            'success' => true,
            'message' => 'Invoice report retrieved successfully',
            'data' => [
                'invoices' => $invoices->items(),
                'summary' => $this->calculateInvoiceSummary($filtered),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'has_more_pages' => $invoices->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * What each invoice's lines cost, as a joinable subquery.
     *
     * Prefers the cost recorded on the line itself: the goods issue consumes
     * FIFO stock layers and writes what the units actually cost, so a batch
     * bought at 20 and one bought at 30 are costed as they were bought, and
     * closing the sale fixes the figure. Re-pricing a product afterwards used
     * to rewrite the reported margin of every invoice it had ever appeared in.
     *
     * A line with no cost on it falls back to quantity against the product's
     * current cost_price. That is a valuation rather than a cost, so the two
     * are counted separately: `estimated_lines` says how much of the figure is
     * a fallback, and `uncosted_lines` how much of it is nothing at all —
     * a product with no cost on file contributes zero, which reads as pure
     * profit. 1038 of 1805 products currently have no cost.
     */
    private function invoiceLineCostQuery()
    {
        return DB::table('invoice_items')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->groupBy('invoice_items.invoice_id')
            ->select('invoice_items.invoice_id')
            ->selectRaw($this->invoiceLineCostExpression().' as total_cost')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('SUM(CASE WHEN invoice_items.total_cost IS NULL THEN 1 ELSE 0 END) as estimated_lines')
            ->selectRaw('SUM(CASE WHEN invoice_items.total_cost IS NULL
                                   AND COALESCE(products.cost_price, 0) <= 0
                                  THEN 1 ELSE 0 END) as uncosted_lines');
    }

    /**
     * The measured cost of a set of invoice lines, estimated where it is
     * missing. Needs invoice_items joined to products.
     */
    private function invoiceLineCostExpression(): string
    {
        return 'SUM(COALESCE(
            invoice_items.total_cost,
            invoice_items.quantity * COALESCE(products.cost_price, 0)
        ))';
    }

    /**
     * Hangs cost, profit and margin on the invoices of one page.
     *
     * One grouped query for the whole page rather than a relation walk per row:
     * the table pages at up to 500.
     *
     * Revenue is taken net of tax. Tax charged on a sale is collected on behalf
     * of the authority and owed straight back to it, so counting it as revenue
     * inflates both the profit and the margin of every taxed invoice.
     *
     * Cost comes off the lines where the sale recorded it; see
     * invoiceLineCostQuery() for what happens where it did not.
     */
    private function attachInvoiceProfitability(array $invoices): void
    {
        if ($invoices === []) {
            return;
        }

        $costs = $this->invoiceLineCostQuery()
            ->whereIn('invoice_items.invoice_id', array_map(fn ($invoice) => $invoice->id, $invoices))
            ->get()
            ->keyBy('invoice_id');

        foreach ($invoices as $invoice) {
            $row = $costs->get($invoice->id);

            $netRevenue = (float) $invoice->total - (float) $invoice->tax;
            $cost = (float) ($row->total_cost ?? 0);
            $profit = $netRevenue - $cost;

            $invoice->setAttribute('net_revenue', round($netRevenue, 5));
            $invoice->setAttribute('total_cost', round($cost, 5));
            $invoice->setAttribute('gross_profit', round($profit, 5));
            $invoice->setAttribute('gross_margin', $netRevenue > 0 ? round(($profit / $netRevenue) * 100, 2) : 0);
            $invoice->setAttribute('line_count', (int) ($row->line_count ?? 0));
            // How much of that cost was measured at the moment of sale, and
            // how much the report had to fill in for itself.
            $invoice->setAttribute('estimated_lines', (int) ($row->estimated_lines ?? 0));
            $invoice->setAttribute('uncosted_lines', (int) ($row->uncosted_lines ?? 0));
        }
    }

    /**
     * Revenue, cost and margin off what was actually billed.
     *
     * salesPerformance() reads the same figures off SalesOrder — a pipeline
     * commitment that can be discounted, cancelled or never invoiced at all.
     * This is the invoice-side counterpart, costed the same way (line
     * quantity against the product's current cost_price) but grounded in
     * documents that were actually issued to a customer.
     */
    public function invoicePerformance(Request $request)
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

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Same shape as salesPerformance(): revenue/count straight off
        // invoices, cost through a separate grouped join on invoice_items so
        // an invoice's total is never multiplied by its line count.
        $costQuery = fn () => (clone $query)
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id');

        // Net of tax. Charging tax does not make a sale more profitable — the
        // money is collected for the authority and owed straight back — but
        // counting it here credited every taxed invoice with profit it never
        // made, and inflated the margin on top.
        $totalRevenue = (float) (clone $query)->sum(DB::raw('total - tax'));
        $totalInvoices = (int) (clone $query)->count();
        // Same basis as the invoice table: what the sale recorded, and the
        // catalogue only where it recorded nothing.
        $totalCost = (float) $costQuery()->sum(DB::raw('COALESCE(
            invoice_items.total_cost,
            invoice_items.quantity * COALESCE(products.cost_price, 0)
        )'));
        $grossProfit = $totalRevenue - $totalCost;

        return response()->json([
            'success' => true,
            'message' => 'Invoice performance retrieved successfully',
            'data' => [
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_cost' => $totalCost,
                    'gross_profit' => $grossProfit,
                    'gross_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
                    'total_invoices' => $totalInvoices,
                ],
                'employee_summary' => $this->invoicePerformanceByGroup((clone $query)->whereNotNull('assigned_employee_id'), 'assigned_employee_id', 'employee_id', 'employee_name', Employee::class),
                'customer_summary' => $this->invoicePerformanceByGroup($query, 'customer_id', 'customer_id', 'customer_name', Customer::class),
                'warehouse_summary' => $this->invoicePerformanceByGroup($query, 'warehouse_id', 'warehouse_id', 'warehouse_name', Warehouse::class),
            ],
        ]);
    }

    /** invoicePerformance()'s counterpart to salesPerformanceByGroup() — see there for why revenue and cost are grouped separately. */
    private function invoicePerformanceByGroup($query, string $column, string $idKey, string $nameKey, string $modelClass)
    {
        $revenueRows = (clone $query)
            ->select($column)
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('SUM(total - tax) as total_revenue')
            ->groupBy($column)
            ->get()
            ->keyBy($column);

        $costByGroup = (clone $query)
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->select('invoices.'.$column)
            ->selectRaw($this->invoiceLineCostExpression().' as total_cost')
            ->groupBy('invoices.'.$column)
            ->pluck('total_cost', $column);

        $names = $this->namesFor($modelClass, $revenueRows->keys()->all());

        return $revenueRows->map(function ($row) use ($costByGroup, $names, $column, $idKey, $nameKey) {
            $groupId = $row->{$column};
            $totalRevenue = (float) ($row->total_revenue ?? 0);
            $totalCost = (float) ($costByGroup[$groupId] ?? 0);
            $grossProfit = $totalRevenue - $totalCost;

            return [
                $idKey => (int) $groupId,
                $nameKey => $names[$groupId] ?? 'غير معروف',
                'total_invoices' => (int) ($row->total_invoices ?? 0),
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'gross_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
            ];
        })->values();
    }

    /**
     * Product profitability off invoice lines rather than order lines — see
     * invoicePerformance() for why the two can disagree.
     */
    public function invoiceProductProfitability(Request $request)
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

        $query = Invoice::query()->with(['items.product', 'items.warehouse', 'warehouse']);
        $this->applyInvoiceDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $invoices = $query->get();

        $lineSummary = $invoices->flatMap(function ($invoice) {
            return $invoice->items->map(function ($item) use ($invoice) {
                $product = $item->product;
                $revenue = (float) ($item->unit_price * $item->quantity);
                // What the line was costed at when it sold, falling back to
                // the catalogue for lines that were never costed.
                $cost = $item->total_cost !== null
                    ? (float) $item->total_cost
                    : (float) (($product?->cost_price ?? 0) * ($item->quantity ?? 0));
                $grossProfit = $revenue - $cost;

                // Each line knows which warehouse actually shipped it — a
                // multi-warehouse invoice can have a different one per
                // product. The header's own warehouse_id is a courtesy
                // default for lines that predate per-line tracking, or were
                // never assigned one.
                $warehouse = $item->warehouse ?: $invoice->warehouse;

                return [
                    'product_id' => (int) ($product?->id ?? 0),
                    'product_name' => $product?->name ?? 'غير معروف',
                    'warehouse_id' => (int) ($item->warehouse_id ?? $invoice->warehouse_id ?? 0),
                    'warehouse_name' => $warehouse?->name ?? 'غير معروف',
                    'quantity' => (float) ($item->quantity ?? 0),
                    'total_revenue' => $revenue,
                    'total_cost' => $cost,
                    'gross_profit' => $grossProfit,
                    'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
                ];
            });
        })->filter(fn ($row) => (int) ($row['product_id'] ?? 0) > 0);

        $grouped = $lineSummary->groupBy(fn ($row) => ($row['product_id'].'-'.$row['warehouse_id']));

        $productSummary = $grouped->map(function ($rows) {
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

        $totalRevenue = (float) $productSummary->sum('total_revenue');
        $totalCost = (float) $productSummary->sum('total_cost');
        $grossProfit = $totalRevenue - $totalCost;

        return response()->json([
            'success' => true,
            'message' => 'Invoice product profitability retrieved successfully',
            'data' => [
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_cost' => $totalCost,
                    'gross_profit' => (float) $grossProfit,
                    'gross_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
                    'product_count' => $productSummary->count(),
                    'top_product' => $productSummary->first() ?: null,
                    'lowest_product' => $productSummary->last() ?: null,
                ],
                'product_summary' => $productSummary,
            ],
        ]);
    }

    /** Ranks reps by what they actually billed, not what they put on order. */
    public function invoiceTopPerformers(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Invoice::query()->whereNotNull('assigned_employee_id');
        $this->applyInvoiceDateFilters($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

        $limit = min((int) $request->input('limit', 10) ?: 10, 50);

        $rows = $query
            ->select('assigned_employee_id')
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('AVG(total) as average_invoice_value')
            ->groupBy('assigned_employee_id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get();

        $names = $this->namesFor(Employee::class, $rows->pluck('assigned_employee_id')->all());

        $topEmployees = $rows->map(function ($item) use ($names) {
            return [
                'employee_id' => $item->assigned_employee_id,
                'employee_name' => $names[$item->assigned_employee_id] ?? 'غير معروف',
                'total_invoices' => (int) ($item->total_invoices ?? 0),
                'total_sales' => (float) ($item->total_sales ?? 0),
                'average_invoice_value' => (float) ($item->average_invoice_value ?? 0),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Invoice top performers retrieved successfully',
            'data' => $topEmployees,
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
            $query->where('sales_orders.assigned_employee_id', $request->employee_id);
        }

        $limit = min((int) $request->input('limit', 10) ?: 10, 50);

        $rows = $query
            ->select('assigned_employee_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('assigned_employee_id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get();

        $names = $this->namesFor(Employee::class, $rows->pluck('assigned_employee_id')->all());

        $topEmployees = $rows->map(function ($item) use ($names) {
            return [
                'employee_id' => $item->assigned_employee_id,
                'employee_name' => $names[$item->assigned_employee_id] ?? 'غير معروف',
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
            $query->where('sales_orders.assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('sales_orders.customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('sales_orders.fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('sales_orders.status', $request->status);
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

        // employee_id was validated and then never applied, so exporting while
        // filtered to one rep silently handed back the whole team's invoices —
        // a spreadsheet that disagrees with the screen it was exported from.
        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->employee_id);
        }

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
        // The screen reports profit per invoice; an export that drops it forces
        // the reader to rebuild the column that was the point of looking.
        $this->attachInvoiceProfitability($rows->all());

        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, [
            'invoice_number', 'customer_name', 'warehouse_name', 'created_at', 'status',
            'subtotal', 'tax', 'discount', 'total', 'paid_amount', 'due_amount',
            'net_revenue', 'total_cost', 'gross_profit', 'gross_margin_percent',
            'estimated_lines', 'uncosted_lines',
        ]);

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
                (float) $row->net_revenue,
                (float) $row->total_cost,
                (float) $row->gross_profit,
                (float) $row->gross_margin,
                (int) $row->estimated_lines,
                (int) $row->uncosted_lines,
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
        $totalOrders = (int) $query->count();
        $totalSales = (float) $query->sum('total');

        // Orders that made it into at least one invoice, and what those
        // invoices actually total — a confirmed order is a promise, an invoice
        // is the bill. The gap between the two is unbilled revenue sitting on
        // the books, which the order figures alone can't show.
        $invoicedOrders = (int) (clone $query)->has('invoices')->count();
        $invoicedTotal = (float) (clone $query)
            ->join('invoices', 'invoices.sales_order_id', '=', 'sales_orders.id')
            ->sum('invoices.total');

        return [
            'total_orders' => $totalOrders,
            'total_sales' => $totalSales,
            'total_subtotal' => (float) $query->sum('subtotal'),
            'total_discount' => (float) $query->sum('discount'),
            'total_tax' => (float) $query->sum('tax'),
            'total_shipping' => (float) $query->sum('shipping_cost'),
            'average_order_value' => $totalOrders > 0 ? (float) $query->avg('total') : 0,
            'invoiced_orders' => $invoicedOrders,
            'uninvoiced_orders' => max(0, $totalOrders - $invoicedOrders),
            'total_invoiced' => $invoicedTotal,
            'uninvoiced_amount' => max(0, $totalSales - $invoicedTotal),
        ];
    }

    private function calculateInvoiceSummary($query)
    {
        $count = (int) $query->count();
        $invoiced = (float) $query->sum('total');
        $tax = (float) $query->sum('tax');

        // Cost over the whole filtered set, not just the page — otherwise the
        // profit under the table would describe twenty rows while the table
        // says it matched two hundred. Grouped per invoice in a subquery and
        // then summed, so joining the lines cannot multiply an invoice's total.
        $cost = (float) DB::query()
            ->fromSub(
                (clone $query)->getQuery()
                    ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
                    ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
                    ->select('invoices.id')
                    ->selectRaw($this->invoiceLineCostExpression().' as invoice_cost')
                    ->groupBy('invoices.id'),
                'per_invoice'
            )
            ->sum('invoice_cost');

        // How much of the cost above was measured at the moment of sale. A
        // profit line resting mostly on catalogue prices is a different claim
        // from one resting on what the goods actually cost, and the reader
        // cannot tell them apart from the figure alone.
        $lineBasis = (clone $query)->getQuery()
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('SUM(CASE WHEN invoice_items.total_cost IS NULL THEN 1 ELSE 0 END) as estimated_lines')
            ->selectRaw('SUM(CASE WHEN invoice_items.total_cost IS NULL
                                   AND COALESCE(products.cost_price, 0) <= 0
                                  THEN 1 ELSE 0 END) as uncosted_lines')
            ->first();

        // Net of tax: see attachInvoiceProfitability() for why tax is not
        // revenue.
        $netRevenue = $invoiced - $tax;
        $profit = $netRevenue - $cost;

        return [
            'total_invoices' => $count,
            'total_invoiced' => $invoiced,
            'total_subtotal' => (float) $query->sum('subtotal'),
            'total_discount' => (float) $query->sum('discount'),
            'total_tax' => $tax,
            'paid_amount' => (float) $query->sum('paid_amount'),
            'due_amount' => (float) $query->sum('due_amount'),
            'average_invoice_value' => $count > 0 ? (float) $query->avg('total') : 0,
            'net_revenue' => round($netRevenue, 5),
            'total_cost' => round($cost, 5),
            'gross_profit' => round($profit, 5),
            'gross_margin' => $netRevenue > 0 ? round(($profit / $netRevenue) * 100, 2) : 0,
            'line_count' => (int) ($lineBasis->line_count ?? 0),
            'estimated_lines' => (int) ($lineBasis->estimated_lines ?? 0),
            'uncosted_lines' => (int) ($lineBasis->uncosted_lines ?? 0),
        ];
    }

    private function groupByEmployee($query)
    {
        $rows = $query
            ->select('assigned_employee_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('assigned_employee_id')
            ->get();

        $names = $this->namesFor(Employee::class, $rows->pluck('assigned_employee_id')->all());

        return $rows->map(function ($item) use ($names) {
            return [
                'employee_id' => $item->assigned_employee_id,
                'employee_name' => $names[$item->assigned_employee_id] ?? 'غير معروف',
                'total_orders' => (int) ($item->total_orders ?? 0),
                'total_sales' => (float) ($item->total_sales ?? 0),
                'total_subtotal' => (float) ($item->total_subtotal ?? 0),
                'average_order_value' => (float) ($item->average_order_value ?? 0),
            ];
        });
    }

    private function groupByCustomer($query)
    {
        $rows = $query
            ->select('customer_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('customer_id')
            ->get();

        $names = $this->namesFor(Customer::class, $rows->pluck('customer_id')->all());

        return $rows->map(function ($item) use ($names) {
            return [
                'customer_id' => $item->customer_id,
                'customer_name' => $names[$item->customer_id] ?? 'غير معروف',
                'total_orders' => (int) ($item->total_orders ?? 0),
                'total_sales' => (float) ($item->total_sales ?? 0),
                'total_subtotal' => (float) ($item->total_subtotal ?? 0),
                'average_order_value' => (float) ($item->average_order_value ?? 0),
            ];
        });
    }

    private function groupByWarehouse($query)
    {
        $rows = $query
            ->select('fulfillment_warehouse_id as warehouse_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_sales')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('fulfillment_warehouse_id')
            ->get();

        $names = $this->namesFor(Warehouse::class, $rows->pluck('warehouse_id')->all());

        return $rows->map(function ($item) use ($names) {
            return [
                'warehouse_id' => $item->warehouse_id,
                'warehouse_name' => $names[$item->warehouse_id] ?? 'غير معروف',
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
