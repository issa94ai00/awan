<?php

namespace App\Services\Sales;

use App\Models\EmployeeCommission;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The landing dashboard of a sales rep (User::isSalesScoped): their own
 * business only — what they sold, what is still owed on it, how far they are
 * from this month's target, and what needs them next.
 *
 * "Theirs" is the rule the invoice and order lists use: assigned to their
 * employee record or raised by them.
 */
class RepDashboardService
{
    private const OPEN_ORDER_STATUSES = [
        SalesOrder::STATUS_PENDING,
        SalesOrder::STATUS_CONFIRMED,
        SalesOrder::STATUS_PROCESSING,
    ];

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $employee = $user->employee;
        $today = today();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();
        $recognized = EmployeeCommission::revenueRecognizedStatuses();

        $sales = fn () => $this->invoices($user)->whereIn('invoices.status', $recognized);
        $open = fn () => $this->invoices($user)->where('invoices.status', '!=', Invoice::STATUS_CANCELLED);

        $month = (float) $sales()->where('invoices.created_at', '>=', $monthStart)->sum('total');
        $previousToDate = (float) $sales()->whereBetween('invoices.created_at', [
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow(),
        ])->sum('total');
        $monthCount = (int) $sales()->where('invoices.created_at', '>=', $monthStart)->count();

        $warehouseId = $user->scopedWarehouseId();

        return [
            'scope' => 'rep',
            'profile' => [
                'name' => $user->name,
                'role' => $user->role?->display_name,
                'employee_id' => $employee?->id,
                'warehouse' => $warehouseId ? Warehouse::whereKey($warehouseId)->first(['id', 'name']) : null,
            ],
            'sales' => [
                'today' => (float) $sales()->whereDate('invoices.created_at', $today)->sum('total'),
                'week' => (float) $sales()->where('invoices.created_at', '>=', $weekStart)->sum('total'),
                'month' => $month,
                'previous_month_to_date' => $previousToDate,
                'growth' => $previousToDate > 0 ? round(($month - $previousToDate) / $previousToDate * 100, 1) : null,
                'month_count' => $monthCount,
                'average_value' => $monthCount ? round($month / $monthCount, 2) : 0,
            ],
            'collections' => [
                'collected_month' => (float) Payment::query()
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->whereDate('payment_date', '>=', $monthStart)
                    ->whereIn('invoice_id', $this->invoices($user)->select('invoices.id'))
                    ->sum('amount'),
                'outstanding' => (float) $open()->sum('due_amount'),
                'unpaid_count' => (int) $open()->where('due_amount', '>', 0)->count(),
                'overdue_count' => (int) $open()->where('due_amount', '>', 0)
                    ->whereNotNull('due_date')->whereDate('due_date', '<', $today)->count(),
            ],
            'customers' => [
                'served_month' => (int) $open()->where('invoices.created_at', '>=', $monthStart)
                    ->distinct()->count('customer_id'),
                'total' => (int) $open()->distinct()->count('customer_id'),
            ],
            'orders' => $this->orders($user),
            'target' => $employee ? $this->target($employee->id, $month) : null,
            'trend' => $this->trend($sales(), 30),
            'recent_invoices' => $this->invoiceRows($this->invoices($user)->latest('invoices.created_at')->limit(6)),
            'unpaid_invoices' => $this->invoiceRows($open()->where('due_amount', '>', 0)
                ->orderByRaw('due_date IS NULL, due_date')->orderByDesc('due_amount')->limit(5)),
            'top_customers' => $this->topCustomers($sales()->where('invoices.created_at', '>=', $monthStart)),
            'top_products' => $this->topProducts($sales()->where('invoices.created_at', '>=', $monthStart)),
            'low_stock' => $warehouseId ? $this->lowStock($warehouseId) : [],
        ];
    }

    /** The user's own invoices. */
    public function invoices(User $user): Builder
    {
        $employeeId = $user->employee?->id;

        return Invoice::query()->where(function ($q) use ($user, $employeeId) {
            $q->where('invoices.created_by', $user->id);
            if ($employeeId) {
                $q->orWhere('invoices.assigned_employee_id', $employeeId);
            }
        });
    }

    /** @return array<string, mixed> */
    private function orders(User $user): array
    {
        $employeeId = $user->employee?->id;
        $query = SalesOrder::query()->where(function ($q) use ($user, $employeeId) {
            $q->where('sales_orders.created_by', $user->id);
            if ($employeeId) {
                $q->orWhere('sales_orders.assigned_employee_id', $employeeId);
            }
        });
        if ($warehouseId = $user->scopedWarehouseId()) {
            $query->where('sales_orders.fulfillment_warehouse_id', $warehouseId);
        }

        $byStatus = (clone $query)->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status');

        return [
            'by_status' => collect([
                SalesOrder::STATUS_PENDING, SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_PROCESSING,
                SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_DELIVERED,
            ])->mapWithKeys(fn ($status) => [$status => (int) ($byStatus[$status] ?? 0)]),
            'open_count' => (int) (clone $query)->whereIn('status', self::OPEN_ORDER_STATUSES)->count(),
            'open_value' => (float) (clone $query)->whereIn('status', self::OPEN_ORDER_STATUSES)->sum('total'),
        ];
    }

    /**
     * This month's target and commission, from the commission statement when
     * HR has opened one. Sales are counted live, the way the statement counts
     * them, so the bar moves with each invoice rather than when HR next saves.
     *
     * @return array<string, mixed>|null
     */
    private function target(int $employeeId, float $monthSales): ?array
    {
        $statement = EmployeeCommission::where('employee_id', $employeeId)
            ->whereDate('month', now()->startOfMonth())
            ->first();
        $live = EmployeeCommission::computeSalesForMonth($employeeId, now());
        $target = (float) ($statement?->monthly_target ?? 0);
        $rate = (float) ($statement?->commission_rate ?? 0);
        $daysLeft = (int) now()->diffInDays(now()->endOfMonth()) + 1;

        return [
            'has_statement' => (bool) $statement,
            'monthly_target' => $target,
            'commission_rate' => $rate,
            'sales' => $live['total_sales'],
            'achievement' => $target > 0 ? round($live['total_sales'] / $target * 100, 1) : null,
            'remaining' => max(0, round($target - $live['total_sales'], 2)),
            'days_left' => $daysLeft,
            'needed_per_day' => $target > 0 ? round(max(0, $target - $live['total_sales']) / $daysLeft, 2) : null,
            'commission' => round($live['total_sales'] * $rate / 100, 2),
        ];
    }

    /** Daily totals for the last `$days` days, gaps filled with zero. */
    private function trend(Builder $sales, int $days): array
    {
        $from = today()->subDays($days - 1);
        $rows = $sales->whereDate('invoices.created_at', '>=', $from)
            ->selectRaw('DATE(invoices.created_at) as d, SUM(total) as total, COUNT(*) as c')
            ->groupBy('d')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->d)->toDateString());

        return collect(range(0, $days - 1))->map(function ($i) use ($from, $rows) {
            $date = $from->copy()->addDays($i)->toDateString();

            return [
                'date' => $date,
                'total' => (float) ($rows[$date]->total ?? 0),
                'count' => (int) ($rows[$date]->c ?? 0),
            ];
        })->all();
    }

    private function invoiceRows(Builder $query): array
    {
        $today = today();

        return $query->with('customer:id,name')
            ->get(['invoices.id', 'invoice_number', 'customer_id', 'total', 'paid_amount', 'due_amount', 'status', 'due_date', 'invoices.created_at'])
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->name,
                'total' => (float) $invoice->total,
                'due_amount' => (float) $invoice->due_amount,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date ? Carbon::parse($invoice->due_date)->toDateString() : null,
                'days_overdue' => $invoice->due_date && Carbon::parse($invoice->due_date)->lt($today)
                    ? (int) Carbon::parse($invoice->due_date)->diffInDays($today) : 0,
                'created_at' => $invoice->created_at?->toIso8601String(),
            ])->all();
    }

    private function topCustomers(Builder $sales): array
    {
        return $sales->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->whereNotNull('invoices.customer_id')
            ->groupBy('invoices.customer_id', 'customers.name')
            ->selectRaw('invoices.customer_id as id, customers.name as name, SUM(invoices.total) as total, COUNT(*) as c')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'total' => (float) $row->total, 'count' => (int) $row->c])
            ->all();
    }

    private function topProducts(Builder $sales): array
    {
        return DB::table('invoice_items')
            ->joinSub($sales->select('invoices.id'), 'mine', 'mine.id', '=', 'invoice_items.invoice_id')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_name')
            ->selectRaw('invoice_items.product_id as id, invoice_items.product_name as name, SUM(invoice_items.quantity) as quantity, SUM(invoice_items.total_price) as total')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'quantity' => (float) $row->quantity,
                'total' => (float) $row->total,
            ])->all();
    }

    /** Products in the rep's warehouse at or under their reorder point. */
    private function lowStock(int $warehouseId): array
    {
        return DB::table('warehouse_inventory')
            ->join('products', 'products.id', '=', 'warehouse_inventory.product_id')
            ->where('warehouse_inventory.warehouse_id', $warehouseId)
            ->where('warehouse_inventory.reorder_point', '>', 0)
            ->whereColumn('warehouse_inventory.available_quantity', '<=', 'warehouse_inventory.reorder_point')
            ->orderBy('warehouse_inventory.available_quantity')
            ->limit(5)
            ->get(['products.id', 'products.name_ar', 'products.name_en', 'warehouse_inventory.available_quantity', 'warehouse_inventory.reorder_point'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name_ar ?: $row->name_en,
                'available' => (float) $row->available_quantity,
                'reorder_point' => (float) $row->reorder_point,
            ])->all();
    }
}
