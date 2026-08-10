<?php

namespace App\Http\Controllers\Api\Field;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Field\BranchReplenishmentService;
use App\Services\Field\FieldScope;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ReplenishmentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stock requests a branch raises against the main warehouse.
 *
 * This is a different thing from a sales order and is modelled as one: nothing
 * is sold, no customer exists, no revenue is earned — goods simply move between
 * two of the company's own locations. It runs on `inventory_transfers`, the same
 * records the back office ships against, so a request raised on a phone appears
 * in the warehouse's queue rather than in a mobile-only list nobody reads.
 *
 * The lifecycle is deliberately split between the two ends:
 *
 *   pending            the branch asks; stock is held at the source so it
 *                      cannot be promised twice while the request waits
 *   in transit         the source approved and shipped — the goods are on a
 *                      road, belonging to neither warehouse
 *   ready for pickup   the source approved and set them aside; they are still
 *                      on its shelf until the requester collects
 *   completed          the goods are in the requester's warehouse
 *
 * Approval belongs to the source and receipt to the destination, which is what
 * the two guards in this class enforce. The rules themselves live in
 * ReplenishmentWorkflowService, shared with the back office — they used to be
 * written twice and had already drifted apart.
 *
 * Stock only ever moves through InventoryService, so every leg writes a movement
 * and the warehouse figures stay in step with the audit trail.
 */
class FieldReplenishmentController extends Controller
{
    public function __construct(
        private InventoryService $inventory,
        private BranchReplenishmentService $replenishment,
        private ReplenishmentWorkflowService $workflow,
    ) {
    }

    /**
     * What this branch needs, ready to submit.
     *
     * The screen this feeds answers "what am I running out of?" — a question
     * the app could not previously ask at all. Without it, restocking meant
     * scrolling the whole shelf from memory, and an item nobody happened to
     * look at simply ran out.
     *
     * Each line carries what the main warehouse can actually send, so the
     * seller sees a refusal coming instead of meeting it after submitting.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());

        try {
            $warehouseId = $scope->resolveWarehouseId($request->integer('warehouse_id') ?: null);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 403);
        }

        $data = $this->replenishment->suggestions(
            $warehouseId,
            $scope->supplyWarehouse(),
            $request->filled('search') ? $request->string('search')->toString() : null
        );

        return response()->json([
            'success' => true,
            'message' => $data['summary']['total'] === 0
                ? 'لا توجد أصناف تحتاج تزويداً في مستودعك.'
                : sprintf(
                    '%d صنف يحتاج تزويداً (%d نافد، %d منخفض).',
                    $data['summary']['total'],
                    $data['summary']['out_of_stock'],
                    $data['summary']['low_stock']
                ),
            'data' => ['warehouse_id' => $warehouseId] + $data,
        ]);
    }

    /**
     * Requests this person has a part in.
     *
     * Two sides, and everyone sees both by default:
     *
     *   incoming  raised *by* their warehouse — what is coming to them
     *   outgoing  raised *against* it — what they owe someone else
     *
     * Outgoing was missing entirely, which meant a person working in the main
     * warehouse could not see a single request anyone had made of them. Nothing
     * could be approved from the app at all, and the branch waited on a queue
     * only the back office could read.
     */
    public function index(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());
        $mine = $scope->warehouseIds();

        $direction = $request->input('direction', 'all');

        $query = InventoryTransfer::query()
            ->with([
                'fromWarehouse:id,name,code',
                'toWarehouse:id,name,code',
                'items.product:id,sku,name_ar,name_en',
            ])
            ->where(fn ($q) => match ($direction) {
                'incoming' => $q->whereIn('to_warehouse_id', $mine),
                'outgoing' => $q->whereIn('from_warehouse_id', $mine),
                default => $q->whereIn('to_warehouse_id', $mine)->orWhereIn('from_warehouse_id', $mine),
            });

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('open_only')) {
            $query->whereIn('status', [
                InventoryTransfer::STATUS_PENDING,
                InventoryTransfer::STATUS_IN_TRANSIT,
                InventoryTransfer::STATUS_READY_FOR_PICKUP,
            ]);
        }

        // What the person can actually do about it — the app's action buttons
        // are drawn off this rather than re-deriving the rules client-side.
        if ($request->boolean('awaiting_my_action')) {
            $query->where(function ($q) use ($mine) {
                $q->where(fn ($inner) => $inner
                    ->where('status', InventoryTransfer::STATUS_PENDING)
                    ->whereIn('from_warehouse_id', $mine))
                    ->orWhere(fn ($inner) => $inner
                        ->whereIn('status', [
                            InventoryTransfer::STATUS_IN_TRANSIT,
                            InventoryTransfer::STATUS_READY_FOR_PICKUP,
                        ])
                        ->whereIn('to_warehouse_id', $mine));
            });
        }

        $rows = $query->latest('id')->paginate(min((int) $request->input('per_page', 20) ?: 20, 100));

        return response()->json([
            'success' => true,
            'data' => [
                'requests' => collect($rows->items())->map(fn ($t) => $this->present($t, $mine))->all(),
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

    public function show(Request $request, int $id): JsonResponse
    {
        $transfer = InventoryTransfer::with([
            'fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'items.product:id,sku,name_ar,name_en',
        ])->findOrFail($id);

        $mine = FieldScope::for($request->user())->warehouseIds();

        // Readable from either end: the warehouse being asked needs to open a
        // request as much as the branch that raised it.
        $isEitherEnd = in_array((int) $transfer->to_warehouse_id, $mine, true)
            || in_array((int) $transfer->from_warehouse_id, $mine, true);

        if (!$isEitherEnd) {
            return $this->outOfScope();
        }

        return response()->json(['success' => true, 'data' => ['request' => $this->present($transfer, $mine)]]);
    }

    /**
     * Raises a request against the main warehouse.
     *
     * The source defaults to the warehouse flagged primary — a branch asking
     * "send me stock" means the main store, and making the app name it would
     * only invite it to name the wrong one.
     */
    public function store(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());

        $validated = $request->validate([
            'to_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'from_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'notes' => 'nullable|string|max:1000',
            // What the branch would prefer. Recorded as a request, not a
            // decision: the warehouse holding the goods is the one that knows
            // whether it can spare a driver today, so it confirms or overrides
            // at approval.
            'preferred_fulfillment_method' => 'nullable|in:delivery,pickup',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $destination = $scope->resolveWarehouseId($validated['to_warehouse_id'] ?? null);
            $source = $this->resolveSource($validated['from_warehouse_id'] ?? null, $destination);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        // Checked as a whole before anything is held. The reservation loop below
        // still refuses a line it cannot take — stock moves between this check
        // and that hold — but it is no longer the thing that *reports* the
        // problem. It used to be, and it stopped at the first short product: a
        // seller restocking eight items met the shortages one submission at a
        // time, each attempt rolling the other seven back.
        $shortfalls = $this->replenishment->shortfalls($validated['items'], $source);

        if ($shortfalls !== []) {
            return response()->json([
                'success' => false,
                'message' => $this->replenishment->shortfallSummary($shortfalls, Warehouse::find($source)),
                'data' => ['reason' => 'insufficient_source_stock', 'shortfalls' => $shortfalls],
            ], 422);
        }

        try {
            $transfer = DB::transaction(function () use ($validated, $source, $destination, $request) {
                $transfer = InventoryTransfer::create([
                    'transfer_number' => 'TRF-' . str_pad((string) (((int) InventoryTransfer::max('id')) + 1), 6, '0', STR_PAD_LEFT),
                    'from_warehouse_id' => $source,
                    'to_warehouse_id' => $destination,
                    'status' => InventoryTransfer::STATUS_PENDING,
                    'fulfillment_method' => $validated['preferred_fulfillment_method'] ?? null,
                    'requested_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $products = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))->get()->keyBy('id');

                foreach ($validated['items'] as $item) {
                    $product = $products[$item['product_id']];

                    // Held at the source while the request waits, so the same
                    // units cannot be promised to a sale in the meantime. The
                    // breakdown above has already reported any shortage; this
                    // only catches stock that moved in between.
                    if (!$this->inventory->reserve((int) $item['product_id'], (int) $item['quantity'], $source)) {
                        throw new RuntimeException(sprintf(
                            'تغيّر رصيد "%s" في مستودع المصدر أثناء الإرسال. أعد المحاولة.',
                            $product->name_ar ?? $product->name_en ?? ('#' . $product->id)
                        ));
                    }

                    InventoryTransferItem::create([
                        'transfer_id' => $transfer->id,
                        'product_id' => $product->id,
                        'quantity_requested' => (int) $item['quantity'],
                        // Costed from the catalogue: the app has no business
                        // deciding what the company's own goods are worth.
                        'unit_cost' => (float) ($product->cost_price ?? 0),
                    ]);
                }

                return $transfer;
            });
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        $transfer->load($this->relations());
        $mine = $scope->warehouseIds();

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال طلب التزويد وحُجزت الكميات بانتظار موافقة المستودع.',
            'data' => ['request' => $this->present($transfer, $mine)],
        ], 201);
    }

    /**
     * The source warehouse agrees, and says how the goods will travel.
     *
     * This is the step that was missing: nothing in the app could move a request
     * off "pending", so a branch's request sat until somebody opened the back
     * office. The person who can act on it is whoever works in the warehouse
     * being asked — which is what the guard below checks.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $transfer = InventoryTransfer::with('items.product')->findOrFail($id);
        $mine = FieldScope::for($request->user())->warehouseIds();

        if ($denied = $this->guardSource($request, $transfer)) {
            return $denied;
        }

        $validated = $request->validate([
            'fulfillment_method' => 'required|in:delivery,pickup',
        ]);

        try {
            $effects = $this->workflow->approve(
                $transfer,
                $validated['fulfillment_method'],
                $request->user()->id
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        $transfer->refresh()->load($this->relations());

        return response()->json([
            'success' => true,
            'message' => $effects['method'] === InventoryTransfer::METHOD_DELIVERY
                ? 'تمت الموافقة وشُحنت البضاعة إلى ' . ($transfer->toWarehouse->name ?? 'الفرع') . '.'
                : 'تمت الموافقة والبضاعة جاهزة للاستلام من ' . ($transfer->fromWarehouse->name ?? 'المستودع') . '.',
            'data' => ['request' => $this->present($transfer, $mine), 'effects' => $effects],
        ]);
    }

    /**
     * The requester takes delivery, and the stock lands in their warehouse.
     *
     * Serves both paths. A delivery is being confirmed as arrived; a pickup is
     * being collected at the counter, and the goods leave the source in this
     * same call. Either way the branch's warehouse is holding them afterwards —
     * which is the point of the whole request.
     *
     * Received quantities are taken per line rather than assumed, because a
     * shipment that arrives short, or a rep who takes only part of what they
     * asked for, are both ordinary.
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $transfer = InventoryTransfer::with('items.product')->findOrFail($id);
        $mine = FieldScope::for($request->user())->warehouseIds();

        if ($denied = $this->guard($request, $transfer)) {
            return $denied;
        }

        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|integer',
            'items.*.quantity_received' => 'required_with:items|integer|min:0',
        ]);

        // The app speaks in products; the workflow keys on transfer lines, which
        // is what survives the same product appearing twice on one request.
        $byProduct = collect($validated['items'] ?? [])->keyBy('product_id');
        $byItem = [];

        foreach ($transfer->items as $item) {
            if ($byProduct->has($item->product_id)) {
                $byItem[$item->id] = (int) $byProduct[$item->product_id]['quantity_received'];
            }
        }

        try {
            $effects = $this->workflow->receive($transfer, $byItem, $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        $transfer->refresh()->load($this->relations());

        $message = 'تم تأكيد الاستلام ودخلت الكميات إلى مخزون ' . ($transfer->toWarehouse->name ?? 'الفرع') . '.';

        if ($effects['discrepancies'] !== []) {
            $message .= ' وسُجّل نقص في ' . count($effects['discrepancies']) . ' صنف مقارنةً بما شُحن.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['request' => $this->present($transfer, $mine), 'effects' => $effects],
        ]);
    }

    /**
     * Calls the request off and puts the held stock back on sale.
     *
     * Open to either end: the branch may no longer need the goods, and the
     * warehouse may be unable to spare them. Only while they are still on the
     * source's shelf — once in transit it is a return, not a cancellation.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $transfer = InventoryTransfer::with('items')->findOrFail($id);

        $mine = FieldScope::for($request->user())->warehouseIds();
        $isEitherEnd = in_array((int) $transfer->to_warehouse_id, $mine, true)
            || in_array((int) $transfer->from_warehouse_id, $mine, true);

        if (!$isEitherEnd) {
            return $this->outOfScope();
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $effects = $this->workflow->cancel($transfer, $request->user()->id, $validated['reason'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        $transfer->refresh()->load($this->relations());

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الطلب وأُعيدت الكميات المحجوزة إلى المتاح للبيع.',
            'data' => ['request' => $this->present($transfer, $mine), 'effects' => $effects],
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** @return array<int,string> */
    private function relations(): array
    {
        return [
            'fromWarehouse:id,name,code',
            'toWarehouse:id,name,code',
            'items.product:id,sku,name_ar,name_en',
        ];
    }

    /** @throws RuntimeException */
    private function resolveSource(?int $requested, int $destination): int
    {
        $source = $requested ?: (int) (Warehouse::where('is_primary', true)->value('id') ?? 0);

        if (!$source) {
            throw new RuntimeException('لا يوجد مستودع رئيسي محدد في النظام.');
        }

        if ($source === $destination) {
            throw new RuntimeException('لا يمكن طلب تزويد من المستودع نفسه.');
        }

        return $source;
    }

    /**
     * Receiving belongs to the destination — only the branch the goods are
     * going to can say they arrived.
     */
    private function guard(Request $request, InventoryTransfer $transfer): ?JsonResponse
    {
        $allowed = FieldScope::for($request->user())->warehouseIds();

        if (in_array((int) $transfer->to_warehouse_id, $allowed, true)) {
            return null;
        }

        return $this->outOfScope();
    }

    /**
     * Approving belongs to the source — the warehouse being asked for the goods
     * is the one that decides whether, and how, they go.
     */
    private function guardSource(Request $request, InventoryTransfer $transfer): ?JsonResponse
    {
        $allowed = FieldScope::for($request->user())->warehouseIds();

        if (in_array((int) $transfer->from_warehouse_id, $allowed, true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'الموافقة على هذا الطلب تخصّ مستودع المصدر، وهو خارج نطاق صلاحيتك.',
            'data' => null,
        ], 403);
    }

    private function outOfScope(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'هذا الطلب يخص مستودعاً خارج نطاق صلاحيتك.',
            'data' => null,
        ], 403);
    }

    /**
     * Which end of the transfer the viewer is on.
     *
     * A person can be both — a warehouse asking another warehouse for stock it
     * in turn owes to a third — so "both" is a real answer, not a fallback.
     *
     * @param  array<int,int>  $viewerWarehouseIds
     */
    private function directionFor(InventoryTransfer $t, array $viewerWarehouseIds): string
    {
        $incoming = in_array((int) $t->to_warehouse_id, $viewerWarehouseIds, true);
        $outgoing = in_array((int) $t->from_warehouse_id, $viewerWarehouseIds, true);

        return match (true) {
            $incoming && $outgoing => 'both',
            $incoming => 'incoming',
            $outgoing => 'outgoing',
            default => 'none',
        };
    }

    /**
     * @param  array<int,int>  $viewerWarehouseIds
     * @return array<string,mixed>
     */
    private function present(InventoryTransfer $t, array $viewerWarehouseIds = []): array
    {
        return [
            'id' => $t->id,
            'request_number' => $t->transfer_number,
            'status' => $t->status,
            'status_text' => $t->status_text,
            'fulfillment_method' => $t->fulfillment_method,
            'fulfillment_method_text' => $t->fulfillment_method_text,
            'from_warehouse_id' => (int) $t->from_warehouse_id,
            'from_warehouse_name' => $t->fromWarehouse?->name,
            'to_warehouse_id' => (int) $t->to_warehouse_id,
            'to_warehouse_name' => $t->toWarehouse?->name,
            // Which side of this transfer the caller is standing on. The app
            // draws entirely different actions for the two, and deciding it
            // here means the phone never has to know the scope rules.
            'direction' => $this->directionFor($t, $viewerWarehouseIds),
            // Every button the app might show is answered here rather than
            // re-derived client-side, so the two can never disagree about what
            // is allowed. A rule added to the workflow reaches the UI for free.
            'can_approve' => $t->canApprove()
                && in_array((int) $t->from_warehouse_id, $viewerWarehouseIds, true),
            'can_receive' => $t->canReceive()
                && in_array((int) $t->to_warehouse_id, $viewerWarehouseIds, true),
            'can_cancel' => $t->canCancel(),
            // Where the goods physically are right now — the thing a person
            // actually wants to know when they open the request.
            'is_awaiting_pickup' => $t->status === InventoryTransfer::STATUS_READY_FOR_PICKUP,
            'notes' => $t->notes,
            'requested_at' => $t->requested_at?->toDateTimeString(),
            'approved_at' => $t->approved_at?->toDateTimeString(),
            'shipped_at' => $t->shipped_at?->toDateTimeString(),
            'received_at' => $t->received_at?->toDateTimeString(),
            'items' => $t->items->map(fn ($i) => [
                'product_id' => (int) $i->product_id,
                'sku' => $i->product?->sku,
                'product_name' => $i->product?->name_ar ?? $i->product?->name_en,
                'quantity_requested' => (int) $i->quantity_requested,
                'quantity_shipped' => (int) ($i->quantity_shipped ?? 0),
                'quantity_received' => (int) ($i->quantity_received ?? 0),
            ])->values(),
        ];
    }
}
