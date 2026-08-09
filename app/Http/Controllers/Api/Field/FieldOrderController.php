<?php

namespace App\Http\Controllers\Api\Field;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderStatusHistory;
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
    public function __construct(private SalesOrderWorkflowService $workflow)
    {
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

        $salesOrder->load(['customer', 'items.product:id,sku,name_ar,name_en', 'fulfillmentWarehouse:id,name,code', 'statusHistory.user:id,name']);

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $this->summary($salesOrder) + [
                    'notes' => $salesOrder->notes,
                    'shipping_address' => $salesOrder->shipping_address,
                    'tracking_number' => $salesOrder->tracking_number,
                    'carrier' => $salesOrder->carrier,
                    'items' => $salesOrder->items->map(fn ($i) => [
                        'product_id' => (int) $i->product_id,
                        'sku' => $i->product?->sku,
                        'name' => $i->product?->name_ar ?? $i->product?->name_en,
                        'quantity' => (int) $i->quantity,
                        'unit_price' => (float) $i->unit_price,
                        'discount' => (float) ($i->discount ?? 0),
                        'tax' => (float) ($i->tax ?? 0),
                        'line_total' => round((float) $i->unit_price * (int) $i->quantity - (float) ($i->discount ?? 0) + (float) ($i->tax ?? 0), 2),
                    ])->values(),
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
     * Raises a local order against a warehouse.
     *
     * Prices are taken from the catalogue rather than the request: letting a
     * client post its own unit price means the phone decides what the goods are
     * worth, and a stale app or a tampered payload silently rewrites the sale.
     * A deliberate override is still possible, but it is an explicit discount
     * per line, which is auditable.
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

        try {
            $order = DB::transaction(function () use ($validated, $warehouseId, $scope, $request) {
                $customer = $this->resolveCustomer($validated);

                $products = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))
                    ->get()
                    ->keyBy('id');

                $lines = [];
                $subtotal = 0.0;

                foreach ($validated['items'] as $item) {
                    $product = $products[$item['product_id']];
                    $unitPrice = (float) ($product->price ?: $product->cost_price ?: 0);
                    $discount = (float) ($item['discount'] ?? 0);
                    $lineTotal = $unitPrice * (int) $item['quantity'] - $discount;

                    $lines[] = [
                        'product_id' => $product->id,
                        'quantity' => (int) $item['quantity'],
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'tax' => 0,
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
                    $order->items()->create($line);
                }

                SalesOrderStatusHistory::create([
                    'sales_order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => SalesOrder::STATUS_PENDING,
                    'note' => 'إنشاء طلب من التطبيق الميداني',
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

        $order->refresh()->load(['customer:id,name,phone', 'fulfillmentWarehouse:id,name,code']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الطلب' . ($request->boolean('confirm') ? ' وتأكيده وحجز المخزون.' : '.'),
            'data' => ['order' => $this->summary($order)],
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
