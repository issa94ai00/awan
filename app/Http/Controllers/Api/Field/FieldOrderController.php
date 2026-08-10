<?php

namespace App\Http\Controllers\Api\Field;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderStatusHistory;
use App\Services\Field\BranchOrderSourcingService;
use App\Services\Field\FieldScope;
use App\Services\Sales\SalesOrderWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orders raised from the field app.
 *
 * An order created here is an ordinary sales order — same table, same stages,
 * same invoice, stock and ledger consequences. Nothing about it is "mobile"
 * except where it was typed. That is deliberate: a second order pipeline would
 * mean a second set of rules to keep in step, and the two would drift.
 *
 * Everything that changes an order goes through SalesOrderWorkflowService, so
 * confirming from a phone reserves stock, raises the invoice and posts the
 * entries exactly as confirming from the back office does.
 */
class FieldOrderController extends Controller
{
    public function __construct(
        private SalesOrderWorkflowService $workflow,
        private BranchOrderSourcingService $sourcing,
    ) {
    }

    /**
     * Orders the signed-in person is responsible for.
     *
     * Defaults to everything served by their warehouse — that is what "the
     * branch's orders" means to someone standing in it — and narrows to only
     * their own with `mine=1`.
     */
    public function index(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());

        $query = SalesOrder::query()
            ->with(['customer:id,name,phone', 'fulfillmentWarehouse:id,name,code'])
            ->whereIn('fulfillment_warehouse_id', $scope->warehouseIds());

        if ($request->boolean('mine') && $scope->employee()) {
            $query->where('assigned_employee_id', $scope->employee()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('open_only')) {
            $query->whereNotIn('status', [SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_CANCELLED]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")));
        }

        $rows = $query->latest('id')->paginate(min((int) $request->input('per_page', 20) ?: 20, 100));

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => collect($rows->items())->map(fn ($o) => $this->summary($o))->all(),
                'pagination' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'has_more_pages' => $rows->hasMorePages(),
                ],
            ],
        ]);
    }

    public function show(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        if ($denied = $this->guard($request, $salesOrder)) {
            return $denied;
        }

        $salesOrder->load([
            'customer',
            'items.product:id,sku,name_ar,name_en',
            'items.allocations.warehouse:id,name,code',
            'fulfillmentWarehouse:id,name,code',
            'statusHistory.user:id,name',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $this->summary($salesOrder) + [
                    'notes' => $salesOrder->notes,
                    'shipping_address' => $salesOrder->shipping_address,
                    'tracking_number' => $salesOrder->tracking_number,
                    'carrier' => $salesOrder->carrier,
                    'items' => $salesOrder->items->map(fn ($i) => $this->line($i))->values(),
                ],
                // The same figures the web screens use, so the phone and the
                // desktop tell the operator the same thing.
                'follow_up' => $this->workflow->followUp($salesOrder),
                'diagnostics' => $this->workflow->diagnose($salesOrder),
                'allowed_transitions' => SalesOrderWorkflowService::TRANSITIONS[$salesOrder->status] ?? [],
                'history' => $salesOrder->statusHistory->map(fn ($h) => [
                    'from_status' => $h->from_status,
                    'to_status' => $h->to_status,
                    'note' => $h->note,
                    'by' => $h->user?->name,
                    'at' => $h->created_at?->toDateTimeString(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Where this order's goods would come from, before anything is created.
     *
     * The app calls this as the seller finishes entering the lines, so the
     * shortage is discovered while the customer is still standing there and the
     * quantities can be talked about — rather than after a submit that fails.
     *
     * It writes nothing and holds nothing: availability is read, not claimed.
     * Two reps previewing the same last unit will both be told it is there, and
     * only one of them will get it at confirmation. That is the correct trade —
     * a preview that reserved would let an abandoned draft sit on stock nobody
     * can sell.
     */
    public function preview(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());

        $validated = $request->validate([
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $warehouseId = $scope->resolveWarehouseId($validated['warehouse_id'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 403);
        }

        $plan = $this->sourcing->plan($validated['items'], $warehouseId, $scope->supplyWarehouse());

        return response()->json([
            'success' => true,
            'message' => $plan['blocked_summary']
                ?? $plan['supply_summary']
                ?? 'الكميات المطلوبة متوفرة في مستودعك.',
            'data' => ['sourcing' => $plan],
        ]);
    }

    /**
     * Raises a local order against a warehouse.
     *
     * Prices are taken from the catalogue rather than the request: letting a
     * client post its own unit price means the phone decides what the goods are
     * worth, and a stale app or a tampered payload silently rewrites the sale.
     * A deliberate override is still possible, but it is an explicit discount
     * per line, which is auditable.
     *
     * Sourcing is decided here too, not taken from the request. The client says
     * *whether* the main warehouse may be drawn on (`supply_from_main`); how
     * much comes from where is worked out server-side against live stock, so a
     * plan the app computed a minute ago cannot commit units that have since
     * been sold. An unanswered shortage comes back as 422 carrying the full
     * breakdown, which is what the app turns into its prompt.
     */
    public function store(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());

        $validated = $request->validate([
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'required_without:customer_id|nullable|string|max:255',
            'customer_phone' => 'required_without:customer_id|nullable|string|max:30',
            'fulfillment_type' => 'nullable|in:ship,pickup,delivery',
            'expected_delivery' => 'nullable|date',
            'shipping_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'confirm' => 'nullable|boolean',
            // The seller's answer to "shall I take the rest from the main
            // warehouse?". Absent means no, and a short order is refused.
            'supply_from_main' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        try {
            $warehouseId = $scope->resolveWarehouseId($validated['warehouse_id'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 403);
        }

        $allowSupply = (bool) ($validated['supply_from_main'] ?? false);

        // Planned with the main warehouse in view whatever the seller answered.
        // Planning without it when they have not opted in would make every
        // shortfall look like stock that does not exist, and the seller would be
        // told to cut the order rather than be offered the top-up that would
        // save it. What the answer decides is whether the plan is *acted on*,
        // below — not what the figures say.
        $plan = $this->sourcing->plan($validated['items'], $warehouseId, $scope->supplyWarehouse());

        // Beyond the reach of both warehouses. Trimming the order to what can be
        // found would quietly sell the customer less than they asked for, so the
        // quantities go back for the seller to change.
        if (!$plan['can_fulfil']) {
            return response()->json([
                'success' => false,
                'message' => $plan['blocked_summary'],
                'data' => ['reason' => 'insufficient_stock', 'sourcing' => $plan],
            ], 422);
        }

        // The branch is short, the main warehouse can cover it, and nobody has
        // said yes yet. This is the prompt, not an error: the app shows it and
        // calls back with `supply_from_main`.
        if ($plan['needs_supply'] && !$allowSupply) {
            return response()->json([
                'success' => false,
                'message' => $plan['supply_summary'],
                'data' => ['reason' => 'supply_confirmation_required', 'sourcing' => $plan],
            ], 422);
        }

        // Summed, not overwritten: the sourcing plan merges repeated lines for
        // the same product into one, so their discounts have to merge with them
        // or the seller loses whichever they entered second.
        $discountByProduct = [];
        foreach ($validated['items'] as $item) {
            $productId = (int) $item['product_id'];
            $discountByProduct[$productId] = ($discountByProduct[$productId] ?? 0) + (float) ($item['discount'] ?? 0);
        }

        $sourcedLines = $this->sourcing->buildLines($plan, $discountByProduct);

        try {
            $order = DB::transaction(function () use ($validated, $sourcedLines, $warehouseId, $scope, $request) {
                $customer = $this->resolveCustomer($validated);

                $products = Product::whereIn('id', collect($sourcedLines)->pluck('product_id'))
                    ->get()
                    ->keyBy('id');

                $lines = [];
                $subtotal = 0.0;

                foreach ($sourcedLines as $line) {
                    $product = $products[$line['product_id']];
                    $unitPrice = (float) ($product->price ?: $product->cost_price ?: 0);
                    $discount = (float) $line['discount'];
                    $lineTotal = $unitPrice * (int) $line['quantity'] - $discount;

                    $lines[] = [
                        'product_id' => $product->id,
                        'quantity' => (int) $line['quantity'],
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'tax' => 0,
                        'description' => $line['description'],
                        // Not a column — carried alongside so the allocation can
                        // be written once the line has an id.
                        'source_warehouse_id' => (int) $line['warehouse_id'],
                    ];
                    $subtotal += $lineTotal;
                }

                $order = SalesOrder::create([
                    // Derived from the last id, not a count: counting reuses a
                    // number the moment any order is deleted.
                    'order_number' => 'SO-' . str_pad((string) (((int) SalesOrder::max('id')) + 1), 6, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'status' => SalesOrder::STATUS_PENDING,
                    'order_date' => now()->toDateString(),
                    'expected_delivery' => $validated['expected_delivery'] ?? null,
                    'subtotal' => round($subtotal, 2),
                    'discount' => (float) ($validated['discount'] ?? 0),
                    'tax' => (float) ($validated['tax'] ?? 0),
                    'shipping_cost' => (float) ($validated['shipping_cost'] ?? 0),
                    'total' => round($subtotal - (float) ($validated['discount'] ?? 0)
                        + (float) ($validated['tax'] ?? 0) + (float) ($validated['shipping_cost'] ?? 0), 2),
                    'currency' => 'SAR',
                    'fulfillment_type' => $validated['fulfillment_type'] ?? SalesOrder::FULFILLMENT_PICKUP,
                    'fulfillment_warehouse_id' => $warehouseId,
                    'assigned_employee_id' => $scope->employee()?->id,
                    'shipping_address' => $validated['shipping_address'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                foreach ($lines as $line) {
                    $sourceWarehouseId = $line['source_warehouse_id'];
                    unset($line['source_warehouse_id']);

                    $item = $order->items()->create($line);

                    // Every line says where it is picked from, including the
                    // ordinary branch-only case. Leaving that implicit would
                    // work — the workflow falls back to the order's warehouse —
                    // but only until someone re-routes the order, at which point
                    // the branch's units would silently follow it somewhere the
                    // seller never chose.
                    $item->allocations()->create([
                        'warehouse_id' => $sourceWarehouseId,
                        'quantity' => (int) $line['quantity'],
                        'status' => 'pending',
                    ]);
                }

                $supplyLines = collect($lines)->where('description', '!=', null);

                SalesOrderStatusHistory::create([
                    'sales_order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => SalesOrder::STATUS_PENDING,
                    'note' => $supplyLines->isEmpty()
                        ? 'إنشاء طلب من التطبيق الميداني'
                        : 'إنشاء طلب من التطبيق الميداني مع ' . $supplyLines->count()
                            . ' بند مكمِّل من المستودع الرئيسي',
                    'user_id' => $request->user()->id,
                ]);

                return $order;
            });

            // Confirming in the same call is what a counter sale needs: the goods
            // are handed over there and then. It is opt-in, because a quotation
            // taken in the field must not reserve stock.
            if ($request->boolean('confirm')) {
                $this->workflow->transitionTo($order->load('items.product'), SalesOrder::STATUS_CONFIRMED);
            }
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        $order->refresh()->load([
            'customer:id,name,phone',
            'fulfillmentWarehouse:id,name,code',
            'items.product:id,sku,name_ar,name_en',
            'items.allocations.warehouse:id,name,code',
        ]);

        $supplied = $order->items->filter(fn ($item) => $item->description !== null);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الطلب' . ($request->boolean('confirm') ? ' وتأكيده وحجز المخزون.' : '.')
                . ($supplied->isEmpty()
                    ? ''
                    : ' وأُضيف ' . $supplied->count() . ' بند مكمِّل من '
                        . ($plan['supply_warehouse']['name'] ?? 'المستودع الرئيسي') . '.'),
            'data' => [
                'order' => $this->summary($order),
                // The split as it was actually written, so the app confirms what
                // happened rather than replaying what it asked for.
                'lines' => $order->items->map(fn ($item) => $this->line($item))->values(),
                'has_supply_lines' => $supplied->isNotEmpty(),
            ],
        ], 201);
    }

    /**
     * Moves the order to the next stage, with every consequence that carries.
     * The rules, the guards and the messages are the workflow service's — this
     * is the same door the web app uses.
     */
    public function transition(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        if ($denied = $this->guard($request, $salesOrder)) {
            return $denied;
        }

        $validated = $request->validate([
            'status' => 'required|in:confirmed,processing,shipped,delivered,cancelled',
            'note' => 'nullable|string|max:500',
            'tracking_number' => 'nullable|string|max:120',
            'carrier' => 'nullable|string|max:120',
            'assigned_employee_id' => 'nullable|exists:employees,id',
            // Cash on delivery: the rep at the door collects and the books
            // record it through the same path the office uses.
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

        $salesOrder->refresh()->load(['customer:id,name,phone', 'fulfillmentWarehouse:id,name,code']);

        return response()->json([
            'success' => true,
            'message' => $result['changed'] ? 'تم نقل الطلب إلى المرحلة المطلوبة.' : 'الطلب في هذه المرحلة بالفعل.',
            'data' => [
                'order' => $this->summary($salesOrder),
                'effects' => $result['effects'] ?? [],
                'allowed_transitions' => SalesOrderWorkflowService::TRANSITIONS[$salesOrder->status] ?? [],
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Finds or opens the customer. Matching on phone keeps a walk-in from
     * becoming a new customer record on every visit.
     */
    private function resolveCustomer(array $validated): Customer
    {
        if (!empty($validated['customer_id'])) {
            return Customer::findOrFail($validated['customer_id']);
        }

        $phone = $validated['customer_phone'] ?? null;

        $existing = $phone ? Customer::where('phone', $phone)->first() : null;
        if ($existing) {
            return $existing;
        }

        return Customer::create([
            'name' => $validated['customer_name'],
            'phone' => $phone,
        ]);
    }

    /** Refuses an order that belongs to a warehouse outside the caller's scope. */
    private function guard(Request $request, SalesOrder $order): ?JsonResponse
    {
        $allowed = FieldScope::for($request->user())->warehouseIds();

        if (in_array((int) $order->fulfillment_warehouse_id, $allowed, true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'هذا الطلب يخص مستودعاً خارج نطاق صلاحيتك.',
            'data' => null,
        ], 403);
    }

    /**
     * One order line, with where it is picked from.
     *
     * The source is read from the line's allocations rather than from the
     * order's warehouse, because on a topped-up order those are two different
     * places and the line is the only thing that knows which.
     *
     * @return array<string,mixed>
     */
    private function line($item): array
    {
        $allocations = $item->allocations ?? collect();

        return [
            'item_id' => $item->id,
            'product_id' => (int) $item->product_id,
            'sku' => $item->product?->sku,
            'name' => $item->product?->name_ar ?? $item->product?->name_en,
            'description' => $item->description,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'discount' => (float) ($item->discount ?? 0),
            'tax' => (float) ($item->tax ?? 0),
            'line_total' => round((float) $item->unit_price * (int) $item->quantity
                - (float) ($item->discount ?? 0) + (float) ($item->tax ?? 0), 2),
            // A line the seller's own branch cannot cover carries a description;
            // that is what marks it as the added quantity on screen and in print.
            'is_supply_line' => $item->description !== null,
            'sources' => $allocations->map(fn ($allocation) => [
                'warehouse_id' => (int) $allocation->warehouse_id,
                'warehouse_name' => $allocation->warehouse?->name,
                'quantity' => (int) $allocation->quantity,
            ])->values(),
        ];
    }

    /** @return array<string,mixed> */
    private function summary(SalesOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_text' => $order->status_text,
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer?->name,
            'customer_phone' => $order->customer?->phone,
            'warehouse_id' => (int) $order->fulfillment_warehouse_id,
            'warehouse_name' => $order->fulfillmentWarehouse?->name,
            'fulfillment_type' => $order->fulfillment_type,
            'fulfillment_type_text' => $order->fulfillment_type_text,
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount,
            'tax' => (float) $order->tax,
            'shipping_cost' => (float) $order->shipping_cost,
            'total' => (float) $order->total,
            'currency' => $order->currency ?: 'SAR',
            'order_date' => $order->order_date?->toDateString(),
            'expected_delivery' => $order->expected_delivery?->toDateString(),
            'created_at' => $order->created_at?->toDateTimeString(),
        ];
    }
}
