<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;

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
        ]);

        $query = PurchaseOrder::with(['supplier', 'items.product']);
        $this->applyDateFilters($query, $request);
        $this->applyCommonFilters($query, $request);

        $perPage = min((int) $request->input('per_page', 20) ?: 20, 500);
        $orders = $query->latest('order_date')->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Purchase report retrieved successfully',
            'data' => [
                'purchase_orders' => $orders->items(),
                'summary' => $this->calculateSummary($query->clone()),
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

        $lineTotals = function ($order) {
            return $order->items->reduce(function ($carry, $item) {
                $qty = (float) ($item->quantity ?? 0);
                $carry['cost'] += (float) ($item->unit_price ?? 0) * $qty;
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
                $cost = (float) ($item->unit_price ?? 0) * $qty;
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
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['Order #', 'Date', 'Supplier', 'Status', 'Subtotal', 'Discount', 'Tax', 'Total']);

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

        return [
            'total_orders' => $totalOrders,
            'total_spend' => (float) $query->sum('total'),
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
