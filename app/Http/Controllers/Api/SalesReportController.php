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

        $perPage = min((int) $request->input('per_page', 20) ?: 20, 500);
        $invoices = $query->latest('created_at')->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Invoice report retrieved successfully',
            'data' => [
                'invoices' => $invoices->items(),
                'summary' => $this->calculateInvoiceSummary($query->clone()),
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

        $totalRevenue = (float) (clone $query)->sum('total');
        $totalInvoices = (int) (clone $query)->count();
        $totalCost = (float) $costQuery()->sum(DB::raw('invoice_items.quantity * COALESCE(products.cost_price, 0)'));
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
            ->selectRaw('SUM(total) as total_revenue')
            ->groupBy($column)
            ->get()
            ->keyBy($column);

        $costByGroup = (clone $query)
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->select('invoices.'.$column)
            ->selectRaw('SUM(invoice_items.quantity * COALESCE(products.cost_price, 0)) as total_cost')
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
                $cost = (float) (($product?->cost_price ?? 0) * ($item->quantity ?? 0));
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
        return [
            'total_invoices' => (int) $query->count(),
            'total_invoiced' => (float) $query->sum('total'),
            'total_subtotal' => (float) $query->sum('subtotal'),
            'total_discount' => (float) $query->sum('discount'),
            'total_tax' => (float) $query->sum('tax'),
            'paid_amount' => (float) $query->sum('paid_amount'),
            'due_amount' => (float) $query->sum('due_amount'),
            'average_invoice_value' => $query->count() > 0 ? (float) $query->avg('total') : 0,
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
