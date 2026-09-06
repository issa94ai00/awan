<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors SalesReportController's shape for the buying side. PurchaseOrder
 * plays the role SalesOrder plays there — the only difference worth noting
 * is that a purchase order carries no warehouse of its own (only a receipt
 * does), so there is no warehouse dimension here the way sales has one.
 */
class PurchaseReportController extends Controller
{
    public function purchaseReport(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:1|max:500',
            'group_by' => 'nullable|in:day,week,month,supplier,status',
            'sort' => 'nullable|in:variance_asc,variance_desc,landed_asc,landed_desc',
        ]);

        $query = PurchaseOrder::with(['supplier', 'items.product']);
        $this->applyDateFilters($query, $request);
        $this->applyCommonFilters($query, $request);

        // Snapshotted before any sort join: the summary sums over this, and a
        // join to the lines would multiply every order's total by its line
        // count.
        $filtered = $query->clone();

        $perPage = min((int) $request->input('per_page', 20) ?: 20, 500);
        $sort = $request->input('sort');

        // An order that cost more than it promised to is the thing worth
        // finding, and it is never the newest row. Neither figure is a column
        // — both come off the lines — so the costs have to be joined in.
        if (in_array($sort, ['variance_asc', 'variance_desc', 'landed_asc', 'landed_desc'], true)) {
            $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
            $expression = str_starts_with($sort, 'variance')
                ? 'COALESCE(line_costs.landed_cost, 0) - COALESCE(line_costs.ordered_cost, 0)'
                : 'COALESCE(line_costs.landed_cost, 0)';

            $query->select('purchase_orders.*')
                ->leftJoinSub($this->purchaseLineCostQuery(), 'line_costs', 'line_costs.purchase_order_id', '=', 'purchase_orders.id')
                ->orderByRaw($expression.' '.$direction)
                ->orderBy('purchase_orders.id', 'desc');
        } else {
            $query->latest('order_date')->latest('id');
        }

        $orders = $query->paginate($perPage);

        $this->attachPurchaseCosts($orders->items());

        return response()->json([
            'success' => true,
            'message' => 'Purchase report retrieved successfully',
            'data' => [
                'purchase_orders' => $orders->items(),
                'summary' => $this->calculateSummary($filtered),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'has_more_pages' => $orders->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * What each order promised to pay and what its goods actually cost to
     * land, as a joinable subquery.
     *
     * `ordered_cost` is the commitment: quantity against the price agreed.
     * `landed_cost` is what the receipts settled it at — the quantity that
     * actually arrived, valued at what the stock layers hold, which includes
     * any freight or customs allocated to the delivery afterwards. A line with
     * nothing received against it falls back to its ordered figure, and
     * `pending_lines` says how many did, so the difference between the two
     * columns is never mistaken for a delivery that came in exactly on price.
     */
    private function purchaseLineCostQuery()
    {
        return DB::table('purchase_order_items')
            ->groupBy('purchase_order_items.purchase_order_id')
            ->select('purchase_order_items.purchase_order_id')
            ->selectRaw('SUM(purchase_order_items.quantity * purchase_order_items.unit_price) as ordered_cost')
            ->selectRaw($this->purchaseLandedCostExpression().' as landed_cost')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('SUM(CASE WHEN purchase_order_items.received_cost IS NULL THEN 1 ELSE 0 END) as pending_lines')
            ->selectRaw('SUM(COALESCE(purchase_order_items.received_quantity, 0)) as received_quantity')
            ->selectRaw('SUM(purchase_order_items.quantity) as ordered_quantity');
    }

    /**
     * The settled cost of a set of order lines, falling back to what each was
     * ordered at where nothing has been received against it.
     */
    private function purchaseLandedCostExpression(): string
    {
        return 'SUM(COALESCE(
            purchase_order_items.received_cost,
            purchase_order_items.quantity * purchase_order_items.unit_price
        ))';
    }

    /**
     * Hangs the ordered and landed figures on the orders of one page.
     *
     * One grouped query for the whole page rather than a walk over each
     * order's lines: the table pages at up to 500.
     */
    private function attachPurchaseCosts(array $orders): void
    {
        if ($orders === []) {
            return;
        }

        $costs = $this->purchaseLineCostQuery()
            ->whereIn('purchase_order_items.purchase_order_id', array_map(fn ($order) => $order->id, $orders))
            ->get()
            ->keyBy('purchase_order_id');

        foreach ($orders as $order) {
            $row = $costs->get($order->id);

            $ordered = (float) ($row->ordered_cost ?? 0);
            $landed = (float) ($row->landed_cost ?? 0);

            $order->setAttribute('ordered_cost', round($ordered, 5));
            $order->setAttribute('landed_cost', round($landed, 5));
            // Positive means the goods cost more than the order promised —
            // short-shipped lines, a price changed at the door, or freight
            // loaded on afterwards.
            $order->setAttribute('cost_variance', round($landed - $ordered, 5));
            $order->setAttribute('cost_variance_percent', $ordered > 0 ? round((($landed - $ordered) / $ordered) * 100, 2) : 0);
            $order->setAttribute('line_count', (int) ($row->line_count ?? 0));
            $order->setAttribute('pending_lines', (int) ($row->pending_lines ?? 0));
            $order->setAttribute('ordered_quantity', (int) ($row->ordered_quantity ?? 0));
            $order->setAttribute('received_quantity', (int) ($row->received_quantity ?? 0));
        }
    }

    public function purchaseSummary(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|string|max:50',
            'group_by' => 'nullable|in:day,week,month,supplier,status',
        ]);

        $query = PurchaseOrder::query();
        $this->applyDateFilters($query, $request);
        $this->applyCommonFilters($query, $request);

        $groupBy = $request->input('group_by', 'day');

        // Cloned: the grouping helpers add their own select/groupBy to the
        // builder they're handed, and a builder mutates in place — reusing
        // $query for the overall summary below would count against whatever
        // GROUP BY the chosen dimension left behind.
        $data = match ($groupBy) {
            'supplier' => $this->groupBySupplier($query->clone()),
            'status' => $this->groupByStatus($query->clone()),
            'week' => $this->groupByWeek($query->clone()),
            'month' => $this->groupByMonth($query->clone()),
            'day' => $this->groupByDay($query->clone()),
            default => $this->groupByDay($query->clone()),
        };

        return response()->json([
            'success' => true,
            'message' => 'Purchase summary retrieved successfully',
            'data' => [
                'group_by' => $groupBy,
                'summary' => $data,
                'overall' => $this->calculateSummary($query),
            ],
        ]);
    }

    public function purchaseDimensions(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|string|max:50',
        ]);

        $query = PurchaseOrder::query();
        $this->applyDateFilters($query, $request);
        $this->applyCommonFilters($query, $request);

        return response()->json([
            'success' => true,
            'message' => 'Purchase dimensions retrieved successfully',
            'data' => [
                'supplier_summary' => $this->groupBySupplier($query->clone()),
                'status_summary' => $this->groupByStatus($query->clone()),
                'overall' => $this->calculateSummary($query->clone()),
            ],
        ]);
    }

    /**
     * Cost is what unit_price rolls into on receipt (see InventoryService);
     * "planned revenue" here is what the order's sale_price lines would fetch
     * if sold at that price — a forward-looking margin on the buying
     * decision itself, before a single unit has moved.
     */
    public function purchasePerformance(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|string|max:50',
        ]);

        $query = PurchaseOrder::query()->with(['items', 'supplier']);
        $this->applyDateFilters($query, $request);
        $this->applyCommonFilters($query, $request);

        $orders = $query->get();

        // Cost is what the goods settled at, not what the order asked. Using
        // the asking price left every planned margin overstated by whatever
        // freight had been loaded onto the delivery, and by any difference
        // between what was ordered and what turned up.
        $lineTotals = function ($order) {
            return $order->items->reduce(function ($carry, $item) {
                $qty = (float) ($item->quantity ?? 0);
                $carry['cost'] += $item->received_cost !== null
                    ? (float) $item->received_cost
                    : (float) ($item->unit_price ?? 0) * $qty;
                $carry['revenue'] += (float) ($item->sale_price ?? $item->unit_price ?? 0) * $qty;

                return $carry;
            }, ['cost' => 0.0, 'revenue' => 0.0]);
        };

        $totalCost = 0.0;
        $totalRevenue = 0.0;
        foreach ($orders as $order) {
            $totals = $lineTotals($order);
            $totalCost += $totals['cost'];
            $totalRevenue += $totals['revenue'];
        }

        $summary = [
            'total_cost' => $totalCost,
            'total_planned_revenue' => $totalRevenue,
            'planned_profit' => $totalRevenue - $totalCost,
            'planned_margin' => $totalRevenue > 0 ? round((($totalRevenue - $totalCost) / $totalRevenue) * 100, 2) : 0,
            'total_orders' => $orders->count(),
        ];

        $supplierSummary = $orders->groupBy('supplier_id')->map(function ($group) use ($lineTotals) {
            $cost = 0.0;
            $revenue = 0.0;
            foreach ($group as $order) {
                $totals = $lineTotals($order);
                $cost += $totals['cost'];
                $revenue += $totals['revenue'];
            }
            $profit = $revenue - $cost;

            return [
                'supplier_id' => (int) $group->first()->supplier_id,
                'supplier_name' => $group->first()->supplier?->name ?? 'Unknown',
                'total_orders' => $group->count(),
                'total_cost' => $cost,
                'total_planned_revenue' => $revenue,
                'planned_profit' => $profit,
                'planned_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Purchase performance retrieved successfully',
            'data' => [
                'summary' => $summary,
                'supplier_summary' => $supplierSummary,
            ],
        ]);
    }

    /** Per-product spend and planned margin, analogous to sales' product profitability. */
    public function productSpend(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|string|max:50',
        ]);

        $query = PurchaseOrder::query()->with(['items.product']);
        $this->applyDateFilters($query, $request);
        $this->applyCommonFilters($query, $request);

        $orders = $query->get();

        $rows = $orders->flatMap(function ($order) {
            return $order->items->map(function ($item) {
                $qty = (float) ($item->quantity ?? 0);
                // As in purchasePerformance(): what it settled at, or what
                // it was ordered at while nothing has been received.
                $cost = $item->received_cost !== null
                    ? (float) $item->received_cost
                    : (float) ($item->unit_price ?? 0) * $qty;
                $revenue = (float) ($item->sale_price ?? $item->unit_price ?? 0) * $qty;

                return [
                    'product_id' => (int) ($item->product_id ?? 0),
                    'product_name' => $item->product?->name_ar ?? $item->product_name ?? 'Unknown',
                    'quantity' => $qty,
                    'total_cost' => $cost,
                    'total_planned_revenue' => $revenue,
                ];
            });
        })->filter(fn ($row) => $row['product_id'] > 0);

        $grouped = $rows->groupBy('product_id')->map(function ($group) {
            $cost = $group->sum('total_cost');
            $revenue = $group->sum('total_planned_revenue');
            $profit = $revenue - $cost;

            return [
                'product_id' => $group->first()['product_id'],
                'product_name' => $group->first()['product_name'],
                'quantity' => $group->sum('quantity'),
                'total_cost' => $cost,
                'total_planned_revenue' => $revenue,
                'planned_profit' => $profit,
                'planned_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
            ];
        })->values()->sortByDesc('total_cost')->values();

        $totalCost = (float) $grouped->sum('total_cost');
        $totalRevenue = (float) $grouped->sum('total_planned_revenue');
        $profit = $totalRevenue - $totalCost;

        // "Margin" here ranks only products with real spend — a product with
        // a handful of units and a lucky sale_price would otherwise crowd out
        // the products actually driving the report.
        $byMargin = $grouped->filter(fn ($row) => $row['total_cost'] > 0)->sortBy('planned_margin')->values();

        $summary = [
            'total_cost' => $totalCost,
            'total_planned_revenue' => $totalRevenue,
            'planned_profit' => $profit,
            'planned_margin' => $totalRevenue > 0 ? round(($profit / $totalRevenue) * 100, 2) : 0,
            'product_count' => $grouped->count(),
            'top_spend_product' => $grouped->first() ?: null,
            'lowest_margin_product' => $byMargin->first() ?: null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Product spend retrieved successfully',
            'data' => [
                'summary' => $summary,
                'product_summary' => $grouped,
            ],
        ]);
    }

    public function topSuppliers(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = PurchaseOrder::query()->whereNotNull('supplier_id');
        $this->applyDateFilters($query, $request);

        $limit = min((int) $request->input('limit', 10) ?: 10, 50);

        $topSuppliers = $query
            ->select('supplier_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_spend')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('supplier_id')
            ->orderByDesc('total_spend')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $supplier = Supplier::find($item->supplier_id);

                return [
                    'supplier_id' => $item->supplier_id,
                    'supplier_name' => $supplier ? $supplier->name : 'Unknown',
                    'total_orders' => (int) ($item->total_orders ?? 0),
                    'total_spend' => (float) ($item->total_spend ?? 0),
                    'average_order_value' => (float) ($item->average_order_value ?? 0),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Top suppliers retrieved successfully',
            'data' => $topSuppliers,
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date' => 'nullable|date',
            'date_filter_type' => 'nullable|in:all,today,yesterday,this_week,this_month,last_month,custom',
            'status' => 'nullable|string|max:50',
        ]);

        $query = PurchaseOrder::query()->with('supplier');
        $this->applyDateFilters($query, $request);
        $this->applyCommonFilters($query, $request);

        $rows = $query->latest('order_date')->get();
        // The screen reports what each order settled at against what it
        // promised; an export that drops it forces the reader to rebuild the
        // column that was the point of looking.
        $this->attachPurchaseCosts($rows->all());

        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, [
            'order_number', 'date', 'supplier_name', 'status',
            'subtotal', 'discount', 'tax', 'total',
            'ordered_cost', 'landed_cost', 'cost_variance', 'cost_variance_percent',
            'ordered_quantity', 'received_quantity', 'pending_lines',
        ]);

        foreach ($rows as $row) {
            fputcsv($csv, [
                $row->order_number,
                $row->order_date?->format('Y-m-d') ?? '-',
                $row->supplier?->name ?? '-',
                $row->status,
                (float) $row->subtotal,
                (float) $row->discount,
                (float) $row->tax,
                (float) $row->total,
                (float) $row->ordered_cost,
                (float) $row->landed_cost,
                (float) $row->cost_variance,
                (float) $row->cost_variance_percent,
                (int) $row->ordered_quantity,
                (int) $row->received_quantity,
                (int) $row->pending_lines,
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="purchases-report.csv"',
        ]);
    }

    private function applyCommonFilters($query, Request $request): void
    {
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
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

    private function calculateSummary($query)
    {
        $totalOrders = (int) $query->count();

        // Ordered against landed over the whole filtered set rather than the
        // page, so the variance under the table describes the same orders as
        // the count beside it. Grouped per order in a subquery and then summed,
        // so joining the lines cannot multiply an order's total.
        $costs = DB::query()
            ->fromSub(
                (clone $query)->getQuery()
                    ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                    ->select('purchase_orders.id')
                    ->selectRaw('SUM(purchase_order_items.quantity * purchase_order_items.unit_price) as ordered_cost')
                    ->selectRaw($this->purchaseLandedCostExpression().' as landed_cost')
                    ->groupBy('purchase_orders.id'),
                'per_order'
            )
            ->selectRaw('SUM(ordered_cost) as ordered_cost')
            ->selectRaw('SUM(landed_cost) as landed_cost')
            ->first();

        // How much of the landed figure is settled and how much is still the
        // order's own asking price. A variance computed largely from
        // undelivered lines is a different claim from one computed from
        // deliveries, and the number alone cannot say which it is.
        $lineBasis = (clone $query)->getQuery()
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('SUM(CASE WHEN purchase_order_items.received_cost IS NULL THEN 1 ELSE 0 END) as pending_lines')
            ->first();

        $orderedCost = (float) ($costs->ordered_cost ?? 0);
        $landedCost = (float) ($costs->landed_cost ?? 0);

        return [
            'total_orders' => $totalOrders,
            'total_spend' => (float) $query->sum('total'),
            'ordered_cost' => round($orderedCost, 5),
            'landed_cost' => round($landedCost, 5),
            'cost_variance' => round($landedCost - $orderedCost, 5),
            'cost_variance_percent' => $orderedCost > 0 ? round((($landedCost - $orderedCost) / $orderedCost) * 100, 2) : 0,
            'line_count' => (int) ($lineBasis->line_count ?? 0),
            'pending_lines' => (int) ($lineBasis->pending_lines ?? 0),
            'total_subtotal' => (float) $query->sum('subtotal'),
            'total_discount' => (float) $query->sum('discount'),
            'total_tax' => (float) $query->sum('tax'),
            'average_order_value' => $totalOrders > 0 ? (float) $query->avg('total') : 0,
            'pending_orders' => (int) (clone $query)->whereIn('status', ['pending', 'confirmed', 'processing'])->count(),
            'completed_orders' => (int) (clone $query)->where('status', 'completed')->count(),
            'cancelled_orders' => (int) (clone $query)->where('status', 'cancelled')->count(),
        ];
    }

    private function groupBySupplier($query)
    {
        return $query
            ->select('supplier_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_spend')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy('supplier_id')
            ->get()
            ->map(function ($item) {
                $supplier = Supplier::find($item->supplier_id);

                return [
                    'supplier_id' => $item->supplier_id,
                    'supplier_name' => $supplier ? $supplier->name : 'Unknown',
                    'total_orders' => (int) ($item->total_orders ?? 0),
                    'total_spend' => (float) ($item->total_spend ?? 0),
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
            ->selectRaw('SUM(total) as total_spend')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'status_text' => $this->getStatusText($item->status),
                    'total_orders' => $item->total_orders,
                    'total_spend' => (float) $item->total_spend,
                    'total_subtotal' => (float) $item->total_subtotal,
                ];
            });
    }

    private function groupByDay($query)
    {
        return $query
            ->selectRaw('DATE(order_date) as date')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total) as total_spend')
            ->selectRaw('SUM(subtotal) as total_subtotal')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total_orders' => $item->total_orders,
                    'total_spend' => (float) $item->total_spend,
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
            ->selectRaw('SUM(total) as total_spend')
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
                    'total_spend' => (float) $item->total_spend,
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
            ->selectRaw('SUM(total) as total_spend')
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
                    'total_spend' => (float) $item->total_spend,
                    'total_subtotal' => (float) $item->total_subtotal,
                ];
            });
    }

    private function getStatusText($status)
    {
        return match ($status) {
            'pending' => 'معلق',
            'confirmed' => 'مؤكد',
            'processing' => 'قيد المعالجة',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            default => $status,
        };
    }
}
