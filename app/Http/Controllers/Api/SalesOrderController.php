<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\ProductUnit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderStatusHistory;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Sales\SalesOrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sales orders.
 *
 * The lifecycle — confirming, re-routing, moving through the execution stages —
 * lives in SalesOrderWorkflowService, which owns the stock, invoice and ledger
 * consequences of each move together. This controller only validates input and
 * shapes the response.
 */
class SalesOrderController extends Controller
{
    public function __construct(
        private SalesOrderWorkflowService $workflow,
        private LedgerPostingService $ledger,
    ) {}

    public function index(Request $request)
    {
        // The list shows who, how much, where from and whether it is paid —
        // not the lines. Loading every line and its product for every row cost
        // more than the rest of the page; the count is enough here and the
        // drawer loads the lines.
        $query = SalesOrder::query()
            ->with([
                'customer:id,name,phone,company',
                'fulfillmentWarehouse:id,name',
                'assignedEmployee:id,first_name,last_name',
                'quote:id,quote_number',
                // The live invoice, for the paid / due column.
                'invoices' => fn ($q) => $q->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->select('id', 'sales_order_id', 'invoice_number', 'status', 'total', 'paid_amount'),
            ])
            // select() replaces the column list, so it comes before the count
            // and the subquery that add to it.
            ->select('sales_orders.*')
            ->withCount('items')
            // When the order last moved, read in the same query so the
            // follow-up figures do not cost one lookup per row.
            ->selectSub(
                SalesOrderStatusHistory::selectRaw('MAX(created_at)')->whereColumn('sales_order_id', 'sales_orders.id'),
                'stage_since_at'
            );

        // Search, customer, routing and dates shape the tab counts too, so a
        // badge says how many of *these* orders sit in each stage.
        $this->applyListScope($query, $request);
        $counts = $this->statusCounts(clone $query);
        $totals = $this->listTotals(clone $query);

        if ($request->filled('status')) {
            $query->where('sales_orders.status', $request->status);
        } elseif ($request->boolean('open')) {
            // Under way: confirmed and not yet delivered.
            $query->whereIn('sales_orders.status', [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_PROCESSING, SalesOrder::STATUS_SHIPPED]);
        }

        // Orders past their promised delivery date and still open — the follow-up
        // view's whole purpose.
        if ($request->boolean('overdue')) {
            $this->whereOverdue($query);
        }

        // Overdue, or sitting in one stage longer than it should.
        if ($request->boolean('attention')) {
            $query->where(function ($q) {
                $this->whereOverdue($q);
                $q->orWhere(fn ($stalled) => $this->whereStalled($stalled));
            });
        }

        // Invoiced and not yet paid in full: what is left to collect.
        if ($request->input('payment') === 'due') {
            $query->whereHas('invoices', fn ($q) => $q->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->whereColumn('paid_amount', '<', DB::raw('total - 0.009')));
        } elseif ($request->input('payment') === 'paid') {
            $query->whereHas('invoices', fn ($q) => $q->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->whereColumn('paid_amount', '>=', DB::raw('total - 0.009')));
        }

        $sort = in_array($request->input('sort'), ['order_date', 'total', 'order_number', 'expected_delivery', 'created_at'], true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('sales_orders.'.$sort, $direction)->orderByDesc('sales_orders.id');

        // per_page was ignored, so callers asking for a larger page (the RMA
        // form requests the customer's delivered orders) silently received only
        // the newest 20 and could not find the order they needed.
        $perPage = min((int) $request->input('per_page', 20) ?: 20, 500);

        $salesOrders = $query->paginate($perPage);

        // Follow-up figures and the payment position per row, so the list can
        // flag what is stuck or unpaid without the browser re-deriving either.
        $rows = collect($salesOrders->items())->map(function (SalesOrder $order) {
            $order->setAttribute('follow_up', $this->workflow->followUp($order));
            $order->setAttribute('payment', $this->paymentPosition($order));
            $order->unsetRelation('invoices');

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Sales orders retrieved successfully',
            'data' => [
                'sales_orders' => $rows,
                'status_counts' => $counts,
                'totals' => $totals,
                // The warehouses and reps that actually hold orders, for the
                // filters — asked for once, when the screen opens.
                'options' => $request->boolean('with_options') ? $this->filterOptions() : null,
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

    private function applyListScope($query, Request $request): void
    {
        if ($request->filled('customer_id')) {
            $query->where('sales_orders.customer_id', $request->customer_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('sales_orders.fulfillment_warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('fulfillment_type')) {
            $query->where('sales_orders.fulfillment_type', $request->fulfillment_type);
        }

        if ($request->filled('employee_id')) {
            $query->where('sales_orders.assigned_employee_id', $request->employee_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('sales_orders.order_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sales_orders.order_date', '<=', $request->date_to);
        }

        // Searching used to happen in the browser over whatever page happened to
        // be loaded, so an order on page 2 could not be found at all. It is a
        // filter on the query now, and the pagination reflects the matches.
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('sales_orders.order_number', 'like', "%{$search}%")
                    ->orWhere('sales_orders.tracking_number', 'like', "%{$search}%")
                    ->orWhere('sales_orders.notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%"))
                    ->orWhereHas('invoices', fn ($i) => $i->where('invoice_number', 'like', "%{$search}%"));
            });
        }
    }

    private function whereOverdue($query): void
    {
        $query->whereNotIn('sales_orders.status', [SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_CANCELLED])
            ->whereNotNull('sales_orders.expected_delivery')
            ->whereDate('sales_orders.expected_delivery', '<', now()->toDateString());
    }

    /**
     * In one stage longer than SalesOrderWorkflowService::STALL_DAYS allows,
     * counted from the last move or, failing one, from when it was raised —
     * the same reading followUp() makes per row.
     */
    private function whereStalled($query): void
    {
        $lastMove = '(SELECT MAX(h.created_at) FROM sales_order_status_histories h WHERE h.sales_order_id = sales_orders.id)';

        $query->where(function ($q) use ($lastMove) {
            foreach (SalesOrderWorkflowService::STALL_DAYS as $status => $days) {
                $q->orWhere(fn ($stage) => $stage->where('sales_orders.status', $status)
                    ->whereRaw("COALESCE({$lastMove}, sales_orders.created_at) < ?", [
                        now()->startOfDay()->subDays($days)->toDateTimeString(),
                    ]));
            }
        });
    }

    /**
     * How many orders sit in each stage, plus how many are past due or need
     * attention — across every order the search matches, not just the page.
     */
    private function statusCounts($query): array
    {
        $counts = (clone $query)->reorder()->setEagerLoads([])->getQuery()
            ->select('sales_orders.status', DB::raw('COUNT(*) as total'))
            ->groupBy('sales_orders.status')
            ->pluck('total', 'status');

        $overdue = (clone $query)->reorder()->setEagerLoads([]);
        $this->whereOverdue($overdue);

        $attention = (clone $query)->reorder()->setEagerLoads([])->where(function ($q) {
            $this->whereOverdue($q);
            $q->orWhere(fn ($stalled) => $this->whereStalled($stalled));
        });

        return [
            'all' => (int) $counts->sum(),
            'pending' => (int) ($counts[SalesOrder::STATUS_PENDING] ?? 0),
            'confirmed' => (int) ($counts[SalesOrder::STATUS_CONFIRMED] ?? 0),
            'processing' => (int) ($counts[SalesOrder::STATUS_PROCESSING] ?? 0),
            'shipped' => (int) ($counts[SalesOrder::STATUS_SHIPPED] ?? 0),
            'delivered' => (int) ($counts[SalesOrder::STATUS_DELIVERED] ?? 0),
            'cancelled' => (int) ($counts[SalesOrder::STATUS_CANCELLED] ?? 0),
            'overdue' => $overdue->count(),
            'attention' => $attention->count(),
        ];
    }

    /**
     * The money behind the counts: what open orders are worth, what was
     * delivered this month, and what invoiced orders still owe.
     */
    private function listTotals($query): array
    {
        $base = fn () => (clone $query)->reorder()->setEagerLoads([]);
        $open = [SalesOrder::STATUS_PENDING, SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_PROCESSING, SalesOrder::STATUS_SHIPPED];

        $delivered = $base()->where('sales_orders.status', SalesOrder::STATUS_DELIVERED)
            ->where(fn ($q) => $q->where('sales_orders.delivered_at', '>=', now()->startOfMonth())
                ->orWhere(fn ($legacy) => $legacy->whereNull('sales_orders.delivered_at')
                    ->where('sales_orders.updated_at', '>=', now()->startOfMonth())));

        $invoiced = Invoice::query()
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereIn('sales_order_id', $base()->whereNot('sales_orders.status', SalesOrder::STATUS_CANCELLED)
                ->getQuery()->select('sales_orders.id'))
            ->selectRaw('COALESCE(SUM(CASE WHEN total - paid_amount > 0 THEN total - paid_amount ELSE 0 END), 0) as due')
            ->selectRaw('SUM(CASE WHEN total - paid_amount > 0.009 THEN 1 ELSE 0 END) as due_count')
            ->first();

        return [
            'open_value' => round((float) $base()->whereIn('sales_orders.status', $open)->sum('sales_orders.total'), 2),
            'delivered_month_value' => round((float) (clone $delivered)->sum('sales_orders.total'), 2),
            'delivered_month_count' => (clone $delivered)->count(),
            'to_collect' => round((float) ($invoiced->due ?? 0), 2),
            'to_collect_count' => (int) ($invoiced->due_count ?? 0),
        ];
    }

    private function filterOptions(): array
    {
        $warehouseIds = SalesOrder::whereNotNull('fulfillment_warehouse_id')->distinct()->pluck('fulfillment_warehouse_id');
        $employeeIds = SalesOrder::whereNotNull('assigned_employee_id')->distinct()->pluck('assigned_employee_id');

        return [
            'warehouses' => Warehouse::whereIn('id', $warehouseIds)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::whereIn('id', $employeeIds)->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->name])->values(),
        ];
    }

    /** Invoiced, paid, due — or not invoiced yet. */
    private function paymentPosition(SalesOrder $order): ?array
    {
        $invoice = $order->invoices->sortByDesc('id')->first();
        if (! $invoice) {
            return null;
        }

        $total = (float) $invoice->total;
        $paid = (float) $invoice->paid_amount;
        $due = max(0, round($total - $paid, 5));

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total' => $total,
            'paid' => $paid,
            'due' => $due,
            'state' => $due <= 0.009 ? 'paid' : ($paid > 0.009 ? 'partial' : 'unpaid'),
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            // "confirm": save and confirm in one step — the reservation, the
            // invoice and the entry follow at once. A refusal (short stock)
            // still leaves the order saved, as a draft, and says why.
            'execute' => 'nullable|in:confirm',
            'assigned_employee_id' => 'nullable|exists:employees,id',
            'fulfillment_warehouse_id' => 'nullable|exists:warehouses,id',
            'fulfillment_type' => 'nullable|in:ship,pickup,delivery',
            'order_date' => 'nullable|date',
            'expected_delivery' => 'nullable|date|after:order_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
            'items.*.product_unit_id' => 'nullable|integer|exists:product_units,id',
            'items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            // Which warehouses fill each line, as the new-order wizard planned
            // it. Optional: an order saved without one is routed at
            // confirmation, as before.
            'items.*.allocations' => 'nullable|array',
            'items.*.allocations.*.warehouse_id' => 'required|integer|exists:warehouses,id',
            'items.*.allocations.*.quantity' => 'required|integer|min:1',
        ]);

        // Who the order belongs to comes from the caller: the back office files
        // orders on behalf of the rep who took them, and the apps send their own
        // employee. Omitting it falls back to the signed-in user's employee
        // record rather than leaving the order unattributed — an order nobody
        // owns has no one to chase it, and drops out of "my orders" everywhere.
        $validated['assigned_employee_id'] = $validated['assigned_employee_id']
            ?? Employee::where('user_id', auth()->id())->value('id');

        // Derived from the last id, not the row count: counting reuses a number
        // the moment any order is deleted.
        $validated['order_number'] = 'SO-'.str_pad(
            (string) (((int) SalesOrder::max('id')) + 1),
            6,
            '0',
            STR_PAD_LEFT
        );
        $validated['status'] = SalesOrder::STATUS_PENDING;
        $validated['created_by'] = auth()->id();

        if (! empty($validated['assigned_employee_id']) && empty($validated['fulfillment_warehouse_id'])) {
            $employee = Employee::find($validated['assigned_employee_id']);
            $validated['fulfillment_warehouse_id'] = $employee?->warehouse_id;
        }

        // No blind fallback to the first active warehouse. An order nobody has
        // routed stays unrouted until confirmation, which picks the warehouse —
        // or the set of them — that can actually fill it.

        $lineItems = $this->buildLineItems($request->items);
        $subtotal = collect($lineItems)->sum(
            fn (array $item) => ($item['unit_price'] * $item['quantity']) - $item['discount'] + $item['tax']
        );

        $validated['subtotal'] = $subtotal;
        // Delivery charged to the customer belongs in what they owe. It was
        // stored on the order but left out of the total, so every shipped order
        // was invoiced for less than it was worth.
        $validated['total'] = $subtotal
            - ($validated['discount'] ?? 0)
            + ($validated['tax'] ?? 0)
            + ($validated['shipping_cost'] ?? 0);

        unset($validated['execute']);

        try {
            $salesOrder = DB::transaction(function () use ($validated, $lineItems, $request) {
                $salesOrder = SalesOrder::create($validated);

                $created = [];
                foreach ($lineItems as $item) {
                    $created[] = $salesOrder->items()->create($item);
                }

                // Opens the stage history, so the trail starts where the order
                // does rather than at whatever its first transition happens to be.
                //
                // Creation is a draft: no stock reservation, no invoice, no
                // ledger posting. Those start only when the order is confirmed.
                SalesOrderStatusHistory::create([
                    'sales_order_id' => $salesOrder->id,
                    'from_status' => null,
                    'to_status' => SalesOrder::STATUS_PENDING,
                    'note' => 'إنشاء الطلب',
                    'user_id' => auth()->id(),
                ]);

                // A plan that breaks the routing rules takes the order with
                // it: better no order than one routed other than as shown.
                $this->workflow->applyInitialPlan($salesOrder, $this->planFrom($created, $request->items));

                return $salesOrder;
            });
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        $execution = $request->input('execute') === 'confirm'
            ? $this->confirmNow($salesOrder)
            : null;

        $salesOrder->refresh()->load(['customer', 'creator', 'items.product', 'items.productUnit', 'items.variant', 'items.allocations']);

        return response()->json([
            'success' => true,
            'message' => $execution && ! $execution['confirmed']
                ? 'تم حفظ الطلب كمسودة، لكن تعذّر تأكيده.'
                : ($execution ? 'تم إنشاء طلب البيع وتأكيده' : 'تم إنشاء طلب البيع بنجاح'),
            'data' => $salesOrder,
            'execution' => $execution,
        ], 201);
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'creator', 'items.product', 'items.variant', 'items.productUnit', 'items.allocations', 'quote', 'fulfillmentWarehouse']);

        return response()->json([
            'success' => true,
            'message' => 'Sales order retrieved successfully',
            'data' => $salesOrder,
        ]);
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        // Editing is only safe while the order is still a plan. Once it is
        // confirmed it has a reservation, an invoice and a posted entry behind
        // it, and silently rewriting the lines here left all three describing
        // quantities and amounts the order no longer had.
        if ($salesOrder->status !== SalesOrder::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل بنود طلب بعد تأكيده. ألغِ الطلب أو أنشئ إشعاراً دائناً بدلاً من ذلك.',
                'data' => null,
            ], 422);
        }

        $validated = $request->validate([
            // Header fields are optional so item-only saves need not restate
            // customer/shipping/notes — missing keys keep the current values.
            'customer_id' => 'sometimes|nullable|exists:customers,id',
            'assigned_employee_id' => 'nullable|exists:employees,id',
            'fulfillment_warehouse_id' => 'nullable|exists:warehouses,id',
            'fulfillment_type' => 'nullable|in:ship,pickup,delivery',
            'order_date' => 'nullable|date',
            'expected_delivery' => 'nullable|date|after:order_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
            'items.*.product_unit_id' => 'nullable|integer|exists:product_units,id',
            'items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            // Which warehouses fill each line, as the new-order wizard planned
            // it. Optional: an order saved without one is routed at
            // confirmation, as before.
            'items.*.allocations' => 'nullable|array',
            'items.*.allocations.*.warehouse_id' => 'required|integer|exists:warehouses,id',
            'items.*.allocations.*.quantity' => 'required|integer|min:1',
        ]);

        // The stage is moved through the workflow endpoints, never by writing
        // the column — a status set here would skip the stock and ledger work
        // that the stage is supposed to trigger.
        unset($validated['status']);

        $headerKeys = [
            'customer_id',
            'assigned_employee_id',
            'fulfillment_warehouse_id',
            'fulfillment_type',
            'order_date',
            'expected_delivery',
            'discount',
            'tax',
            'shipping_cost',
            'shipping_address',
            'notes',
        ];

        foreach ($headerKeys as $key) {
            if (! array_key_exists($key, $validated)) {
                $validated[$key] = $salesOrder->{$key};
            }
        }

        // Reassignment is a real back-office action — an order moves to whoever
        // is covering the round. A request that leaves the field out keeps the
        // current owner rather than clearing it, so editing a delivery address
        // cannot silently orphan the order.
        $hasAssignedEmployeeInput = $request->has('assigned_employee_id');
        if (! $hasAssignedEmployeeInput) {
            $validated['assigned_employee_id'] = $salesOrder->assigned_employee_id;
        }

        if ($validated['customer_id'] === null) {
            $validated['customer_id'] = $salesOrder->customer_id;
        }

        $hasWarehouseInput = $request->has('fulfillment_warehouse_id');

        $lineItems = $this->buildLineItems($request->items);
        $subtotal = collect($lineItems)->sum(
            fn (array $item) => ($item['unit_price'] * $item['quantity']) - $item['discount'] + $item['tax']
        );

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal
            - ($validated['discount'] ?? 0)
            + ($validated['tax'] ?? 0)
            + ($validated['shipping_cost'] ?? 0);

        // Derived from whoever the order now belongs to — which may be a rep it
        // was just reassigned to, so the warehouse follows the round rather than
        // staying with the person who happened to raise it.
        if (! $hasWarehouseInput && ! empty($validated['assigned_employee_id']) && (int) $validated['assigned_employee_id'] !== (int) $salesOrder->assigned_employee_id) {
            $validated['fulfillment_warehouse_id'] = Employee::find($validated['assigned_employee_id'])?->warehouse_id ?? $salesOrder->fulfillment_warehouse_id;
        }

        if (! $hasWarehouseInput && empty($validated['fulfillment_warehouse_id']) && ! empty($salesOrder->fulfillment_warehouse_id)) {
            $validated['fulfillment_warehouse_id'] = $salesOrder->fulfillment_warehouse_id;
        }

        try {
            DB::transaction(function () use ($salesOrder, $validated, $lineItems, $request) {
                $salesOrder->update($validated);

                // The lines are rewritten, and their allocations with them; the
                // warehouses the order was routed through go too when a plan is
                // sent, so it is the whole plan rather than laid over the old.
                $salesOrder->items()->delete();
                $created = [];
                foreach ($lineItems as $item) {
                    $created[] = $salesOrder->items()->create($item);
                }

                $plan = $this->planFrom($created, $request->items);
                if (array_filter($plan)) {
                    $salesOrder->routings()->sync([]);
                    $this->workflow->applyInitialPlan($salesOrder->refresh(), $plan);
                }
            });
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        $salesOrder->load(['customer', 'creator', 'items.product', 'items.productUnit', 'items.variant']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث طلب البيع بنجاح',
            'data' => $salesOrder,
        ]);
    }

    /**
     * Where each line of an order being written should come from — the
     * wizard's routing step asks before saving.
     */
    public function suggestRouting(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'fulfillment_warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->workflow->suggestSourcing(
                $validated['items'],
                isset($validated['fulfillment_warehouse_id']) ? (int) $validated['fulfillment_warehouse_id'] : null,
            ),
        ]);
    }

    /**
     * The plan sent with the lines, keyed by the lines just written.
     *
     * @param  list<\App\Models\SalesOrderItem>  $created  in request order
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<int,int>>  item id => [warehouse id => quantity]
     */
    private function planFrom(array $created, array $items): array
    {
        $plan = [];

        foreach (array_values($items) as $index => $item) {
            $line = $created[$index] ?? null;
            if (! $line || empty($item['allocations'])) {
                continue;
            }

            foreach ($item['allocations'] as $allocation) {
                $warehouseId = (int) $allocation['warehouse_id'];
                $plan[$line->id][$warehouseId] = ($plan[$line->id][$warehouseId] ?? 0) + (int) $allocation['quantity'];
            }
        }

        return $plan;
    }

    /**
     * Confirms an order just saved, reporting a refusal instead of throwing:
     * the order stays, as a draft, with the reason and — for short stock —
     * what is missing where.
     *
     * @return array{confirmed: bool, message?: string, shortages?: array, effects?: array}
     */
    private function confirmNow(SalesOrder $salesOrder): array
    {
        try {
            $result = $this->workflow->transitionTo($salesOrder->refresh(), SalesOrder::STATUS_CONFIRMED);

            return ['confirmed' => true, 'effects' => $result['effects'] ?? $result];
        } catch (RuntimeException $e) {
            return [
                'confirmed' => false,
                'message' => $e->getMessage(),
                'shortages' => $this->workflow->stockShortages($salesOrder->refresh()),
            ];
        }
    }

    /**
     * Resolve unit metadata onto each line so create/update persist the same shape.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function buildLineItems(array $items): array
    {
        $unitIds = collect($items)->pluck('product_unit_id')->filter()->unique()->all();
        $units = $unitIds === []
            ? collect()
            : ProductUnit::query()->whereIn('id', $unitIds)->get()->keyBy('id');

        // A line for one variant carries its name ("floor drain - 4\"") so the
        // order, its invoice and the picking list all say which size.
        $variants = ProductVariant::forLines($items);
        $variantProducts = $variants->isEmpty()
            ? collect()
            : Product::whereIn('id', $variants->pluck('product_id'))->get(['id', 'name_ar', 'name_en'])->keyBy('id');

        $lines = [];

        foreach ($items as $item) {
            $variant = $variants->get((int) ($item['product_variant_id'] ?? 0));
            $unitName = null;
            $unitMultiplier = 1;
            $unitId = $item['product_unit_id'] ?? null;

            if (! empty($unitId)) {
                $unit = $units->get($unitId);
                if ($unit) {
                    $unitName = $unit->name_ar ?: $unit->name;
                    $unitMultiplier = (float) $unit->base_unit_multiplier;
                } else {
                    $unitId = null;
                }
            }

            $product = $variant ? $variantProducts->get($variant->product_id) : null;

            $lines[] = [
                'product_id' => $item['product_id'],
                'product_variant_id' => $variant?->id,
                'description' => $variant ? $variant->displayName($product->name_ar ?? $product->name_en) : null,
                'product_unit_id' => $unitId,
                'unit_name' => $unitName,
                'unit_multiplier' => $unitMultiplier,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
            ];
        }

        return $lines;
    }

    public function destroy(SalesOrder $salesOrder)
    {
        // Deleting a confirmed order would strand its invoice and journal entry
        // with no document behind them, and leave the reserved stock held
        // forever. Cancelling unwinds all three properly.
        if (! in_array($salesOrder->status, [SalesOrder::STATUS_PENDING, SalesOrder::STATUS_CANCELLED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف طلب مؤكد. استخدم الإلغاء ليُحرَّر الحجز وتُعكس القيود.',
                'data' => null,
            ], 422);
        }

        DB::transaction(function () use ($salesOrder) {
            $salesOrder->items()->delete();
            $salesOrder->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'تم حذف طلب البيع بنجاح',
            'data' => null,
        ]);
    }

    /**
     * Returns the order's invoice, raising it if confirmation somehow did not.
     *
     * This endpoint used to build a second invoice unconditionally and call
     * `updateBalance` again with it, so pressing the button twice left the order
     * with duplicate invoices and the customer owing double. Invoicing is now a
     * property of the confirmed order, not of this button.
     */
    public function convertToInvoice(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== SalesOrder::STATUS_CONFIRMED) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن تحويل طلبات البيع المؤكدة فقط إلى فواتير',
                'data' => null,
            ], 400);
        }

        $existing = $this->workflow->existingInvoice($salesOrder);

        $invoice = DB::transaction(function () use ($salesOrder) {
            $invoice = $this->workflow->ensureInvoice($salesOrder->load('items.product', 'customer'));
            $this->ledger->postInvoice($invoice);

            return $invoice;
        });

        $invoice->load(['customer', 'salesOrder', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => $existing
                ? 'هذه الطلبية مرتبطة بالفاتورة '.$invoice->invoice_number.' مسبقاً.'
                : 'تم تحويل طلب البيع إلى فاتورة بنجاح',
            'data' => $invoice,
        ], $existing ? 200 : 201);
    }

    /**
     * Everything the detail screen shows, in one request.
     *
     * The drawer used to render the order row alone: no invoice, no ledger
     * entry, no stock movement. An order could therefore look finished while its
     * revenue had never reached the books, and nothing on the screen said so.
     * The documents that are supposed to follow an order are returned beside it,
     * along with the diagnosis of what is missing.
     */
    public function detail(SalesOrder $salesOrder)
    {
        $salesOrder->load([
            'customer', 'creator', 'assignedEmployee', 'quote',
            'items.product', 'items.variant', 'fulfillmentWarehouse', 'statusHistory.user',
        ]);

        $invoice = $this->workflow->existingInvoice($salesOrder);
        $invoice?->load(['items', 'payments']);

        // Everything the order caused in the ledger: the cost of the goods, the
        // invoice that billed them, and the collections against it. A payment is
        // keyed by its own id, so it has to be looked up by payment rather than
        // by order, or the trail stops at the invoice.
        $paymentKeys = collect($invoice?->payments ?? [])
            ->flatMap(fn ($p) => ['payment:'.$p->id, 'payment:'.$p->id.':reversal']);

        $entries = JournalEntryHeader::with('lines.ledgerAccount')
            ->where(function ($q) use ($salesOrder, $invoice, $paymentKeys) {
                $q->whereIn('posting_key', [
                    'so_cogs:'.$salesOrder->id,
                    'so_cogs:'.$salesOrder->id.':reversal',
                ]);

                if ($invoice) {
                    // Anchored on the colon: a bare "invoice:1%" prefix would
                    // also swallow invoice:10, invoice:19 and so on.
                    $q->orWhere('posting_key', 'invoice:'.$invoice->id)
                        ->orWhere('posting_key', 'like', 'invoice:'.$invoice->id.':%');
                }

                if ($paymentKeys->isNotEmpty()) {
                    $q->orWhereIn('posting_key', $paymentKeys->all());
                }
            })
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'sales_order' => $salesOrder,
                'invoice' => $invoice,
                'payments' => $invoice?->payments ?? [],
                'journal_entries' => $entries,
                'stock_movements' => $this->workflow->movementsFor($salesOrder),
                'diagnostics' => $this->workflow->diagnose($salesOrder),
                'picking_list' => $this->workflow->pickingListFor($salesOrder),
                // All of them: an order routed across two warehouses raises two
                // picking jobs, and the single key above can only show one.
                'picking_lists' => $this->workflow->pickingListsFor($salesOrder),
                'follow_up' => $this->workflow->followUp($salesOrder),
                'history' => $salesOrder->statusHistory,
                'routing' => $this->routingPayload($salesOrder),
                'timeline' => [
                    'confirmed_at' => $salesOrder->confirmed_at,
                    'shipped_at' => $salesOrder->shipped_at,
                    'delivered_at' => $salesOrder->delivered_at,
                    'order_date' => $salesOrder->order_date,
                    'expected_delivery' => $salesOrder->expected_delivery,
                ],
            ],
        ]);
    }

    /**
     * Coverage of the order across warehouses, so the operator can route it with
     * the stock figures in front of them instead of discovering a shortfall when
     * confirmation fails.
     */
    public function routingOptions(SalesOrder $salesOrder)
    {
        return response()->json([
            'success' => true,
            'data' => $this->routingPayload($salesOrder),
        ]);
    }

    /** Shared by the routing endpoint and the detail screen, so they cannot drift. */
    private function routingPayload(SalesOrder $salesOrder): array
    {
        return $this->workflow->routingOptions($salesOrder) + [
            'fulfillment_type' => $salesOrder->fulfillment_type,
            'status' => $salesOrder->status,
            'allowed_transitions' => SalesOrderWorkflowService::TRANSITIONS[$salesOrder->status] ?? [],
            'can_change_fulfillment_type' => ! in_array(
                $salesOrder->status,
                [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_CANCELLED],
                true
            ),
        ];
    }

    /**
     * Replaces the warehouses the order is routed through.
     *
     * More than one may be chosen: an order split across two branches is routed
     * to both, and only those two can then source its lines.
     */
    public function saveRoutings(Request $request, SalesOrder $salesOrder)
    {
        $validated = $request->validate([
            'warehouse_ids' => 'required|array|min:1',
            'warehouse_ids.*' => 'required|integer|exists:warehouses,id',
        ]);

        try {
            $result = $this->workflow->saveRoutings($salesOrder, $validated['warehouse_ids']);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ توجيهات الطلب.',
            'data' => $result,
        ]);
    }

    /** The per-line sourcing plan and what each warehouse could supply. */
    public function sourcing(SalesOrder $salesOrder)
    {
        return response()->json([
            'success' => true,
            'data' => $this->workflow->sourcingPlan($salesOrder),
        ]);
    }

    /**
     * Replaces the sourcing plan. Any stock already held moves with it, so the
     * hold always sits where the goods will actually be taken from.
     */
    public function saveSourcing(Request $request, SalesOrder $salesOrder)
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer',
            'lines.*.sources' => 'required|array|min:1',
            'lines.*.sources.*.warehouse_id' => 'required|integer|exists:warehouses,id',
            'lines.*.sources.*.quantity' => 'required|integer|min:0',
        ]);

        $plan = [];
        foreach ($validated['lines'] as $line) {
            foreach ($line['sources'] as $source) {
                $plan[(int) $line['item_id']][(int) $source['warehouse_id']] = (int) $source['quantity'];
            }
        }

        try {
            $result = $this->workflow->saveSourcingPlan($salesOrder, $plan);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ مصادر البضاعة، ونُقل الحجز ليطابقها.',
            'data' => $result,
        ]);
    }

    /** Moves the order to the next execution stage, with all its side effects. */
    public function transition(Request $request, SalesOrder $salesOrder)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'tracking_number' => 'nullable|string|max:120',
            'carrier' => 'nullable|string|max:120',
            // Kept on the stage history. Required for a cancellation, which the
            // workflow enforces rather than this rule, so the same guard holds
            // for every caller.
            'note' => 'nullable|string|max:500',
            // Hands the order to whoever owns the stage being entered.
            'assigned_employee_id' => 'nullable|exists:employees,id',
            // Collection taken at delivery. `settle` is opt-in because a credit
            // customer paying later is just as ordinary as cash at the door.
            'settle' => 'nullable|boolean',
            'settlement_amount' => 'nullable|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,check',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        try {
            $result = $this->workflow->transitionTo($salesOrder, $validated['status'], $validated);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['changed']
                ? 'تم نقل الطلب إلى مرحلة "'.$this->stageLabel($result['status']).'".'
                : 'الطلب في هذه المرحلة بالفعل.',
            'data' => $this->orderPayload($salesOrder->refresh(), $result),
        ]);
    }

    /** Confirms the order: reserves the stock, raises the invoice, posts the entry. */
    public function confirmOrder(SalesOrder $salesOrder)
    {
        if ($salesOrder->status === SalesOrder::STATUS_CONFIRMED) {
            return response()->json([
                'success' => true,
                'message' => 'تم تأكيد هذه الطلبية مسبقاً.',
                'data' => $this->orderPayload($salesOrder, ['changed' => false, 'status' => $salesOrder->status, 'effects' => []]),
            ]);
        }

        try {
            $result = $this->workflow->transitionTo($salesOrder, SalesOrder::STATUS_CONFIRMED);
        } catch (RuntimeException $e) {
            // A refusal for lack of stock carries the shortfall as data, not
            // only inside the sentence. The operator was otherwise left reading
            // product names out of an error string to retype them into a
            // purchase order; the screen can now offer to raise one.
            $shortages = $this->workflow->stockShortages($salesOrder);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $shortages === [] ? null : ['shortages' => $shortages],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد الطلبية: حُجز المخزون وأُنشئت الفاتورة ورُحّل القيد المحاسبي.',
            'data' => $this->orderPayload($salesOrder->refresh(), $result),
        ]);
    }

    /**
     * What this order asks for that stock cannot cover.
     *
     * Read separately from the confirm attempt so the purchase screen can fetch
     * it by order id and prefill itself, rather than the figures being passed
     * through a URL and going stale on the way.
     */
    public function stockShortages(SalesOrder $salesOrder)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'sales_order' => [
                    'id' => $salesOrder->id,
                    'order_number' => $salesOrder->order_number,
                ],
                'shortages' => $this->workflow->stockShortages($salesOrder),
            ],
        ]);
    }

    /**
     * The order's lines as a purchase order, for the purchase screen to open
     * prefilled — "buy in what this customer ordered".
     */
    public function purchaseDraft(SalesOrder $salesOrder)
    {
        if ($salesOrder->status === SalesOrder::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب ملغى — لا حاجة لشراء بنوده.',
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->workflow->purchaseDraft($salesOrder),
        ]);
    }

    /** Changes how the order is fulfilled, and everything that follows from it. */
    public function changeFulfillmentType(Request $request, SalesOrder $salesOrder)
    {
        $validated = $request->validate([
            'fulfillment_type' => 'required|in:ship,pickup,delivery',
            'fulfillment_warehouse_id' => 'nullable|exists:warehouses,id',
            'shipping_cost' => 'nullable|numeric|min:0',
        ]);

        try {
            $effects = $this->workflow->changeFulfillmentType($salesOrder, $validated['fulfillment_type'], $validated);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير نوع التنفيذ وإعادة توجيه الطلب.',
            'data' => $this->orderPayload($salesOrder->refresh(), ['changed' => true, 'status' => $salesOrder->status, 'effects' => $effects]),
        ]);
    }

    /** Everything the order screen needs after a stage move. */
    private function orderPayload(SalesOrder $salesOrder, array $result): array
    {
        $invoice = $this->workflow->existingInvoice($salesOrder);

        return [
            'sales_order' => $salesOrder->load(['customer', 'items.product', 'fulfillmentWarehouse']),
            'invoice' => $invoice?->load('items.product'),
            'journal_entries' => JournalEntryHeader::with('lines.ledgerAccount')
                ->where(function ($q) use ($salesOrder, $invoice) {
                    $q->where('posting_key', 'so_cogs:'.$salesOrder->id);
                    if ($invoice) {
                        $q->orWhere('posting_key', 'invoice:'.$invoice->id)
                            ->orWhere('posting_key', 'like', 'invoice:'.$invoice->id.':%');
                    }
                })
                ->get(),
            'stock_movements' => $this->workflow->movementsFor($salesOrder),
            'transition' => $result,
        ];
    }

    private function stageLabel(string $status): string
    {
        return [
            SalesOrder::STATUS_PENDING => 'معلق',
            SalesOrder::STATUS_CONFIRMED => 'مؤكد',
            SalesOrder::STATUS_PROCESSING => 'قيد المعالجة',
            SalesOrder::STATUS_SHIPPED => 'تم الشحن',
            SalesOrder::STATUS_DELIVERED => 'تم التسليم',
            SalesOrder::STATUS_CANCELLED => 'ملغي',
        ][$status] ?? $status;
    }
}
