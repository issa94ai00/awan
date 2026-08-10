<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\SalesOrder;
use App\Models\SalesOrderStatusHistory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Inventory\InventoryService;
use App\Services\PickingService;
use App\Services\Sales\PaymentRecorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns what actually happens to a sales order as it moves through its stages.
 *
 * The lifecycle used to live inline in SalesOrderController, and it had three
 * problems that this class exists to fix:
 *
 *  - **The books were wrong.** Journal lines were written straight to hardcoded
 *    account ids 1/2/3, which in a real chart of accounts are equity accounts —
 *    so confirming an order debited owners' equity and credited capital.
 *    Everything financial now goes through LedgerPostingService, which resolves
 *    accounts by posting role and refuses to write an unbalanced entry.
 *  - **Stock left too early and without checking.** Confirming issued the full
 *    quantity out of the warehouse with no availability test, so an order for
 *    stock that did not exist drove the position negative and the goods were
 *    gone from inventory while still sitting on the shelf. Confirming now
 *    *reserves*; the stock only leaves at the shipping stage.
 *  - **Nothing recorded the cost.** Revenue was posted, the matching cost of
 *    goods sold was not, so gross profit equalled the whole sale price.
 *
 * Every side effect is keyed on the document that caused it, so replaying a
 * stage — a double-click, a retried request, a repair run — cannot invoice
 * twice, move stock twice, or post an entry twice.
 */
class SalesOrderWorkflowService
{
    /**
     * Legal forward moves. Mirrors `resources/js/utils/sales.js` so the UI never
     * offers a transition the API will reject.
     *
     * @var array<string,string[]>
     */
    public const TRANSITIONS = [
        SalesOrder::STATUS_PENDING => [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_CONFIRMED => [SalesOrder::STATUS_PROCESSING, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_PROCESSING => [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_SHIPPED => [SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_DELIVERED => [],
        SalesOrder::STATUS_CANCELLED => [],
    ];

    /** Stages at which the goods have already physically left the warehouse. */
    private const SHIPPED_STAGES = [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_DELIVERED];

    /** Stages at which stock is held for the order but has not shipped. */
    private const RESERVED_STAGES = [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_PROCESSING];

    public function __construct(
        private InventoryService $inventory,
        private LedgerPostingService $ledger,
        private PaymentRecorder $payments,
        private PickingService $picking,
    ) {
    }

    /* ------------------------------------------------------------------ *
     * Routing — which warehouse should serve this order
     * ------------------------------------------------------------------ */

    /**
     * Per-warehouse coverage for an order, so the operator can see *before*
     * confirming which warehouse can actually fill it.
     *
     * The old flow silently defaulted the warehouse to "the first active one"
     * in a model hook and only discovered the shortfall when confirming blew up
     * mid-transaction. This surfaces the same information up front.
     *
     * @return array{warehouses:array<int,array<string,mixed>>,recommended_warehouse_id:?int,current_warehouse_id:?int}
     */
    public function routingOptions(SalesOrder $order): array
    {
        // Allocations come along because coverage is now measured against each
        // line's planned sources; loading them here keeps the per-warehouse loop
        // below from firing a query per line.
        $order->loadMissing('items.product', 'items.allocations');

        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('id')->get();

        $rows = $warehouses->map(function (Warehouse $warehouse) use ($order) {
            $items = $order->items->map(function ($item) use ($warehouse, $order) {
                $required = (int) $item->quantity;
                $available = $this->sellableFor((int) $item->product_id, $warehouse->id, $order);

                return [
                    'product_id' => (int) $item->product_id,
                    'product_name' => $item->product->name_ar ?? $item->product->name_en ?? $item->product->name ?? '#' . $item->product_id,
                    'sku' => $item->product->sku ?? null,
                    'required' => $required,
                    'available' => $available,
                    'shortfall' => max(0, $required - $available),
                ];
            })->values();

            $covered = $items->where('shortfall', 0)->count();
            $total = max(1, $items->count());

            return [
                'warehouse_id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'location_type' => $warehouse->location_type,
                'location_type_text' => $warehouse->location_type_text,
                'items' => $items->all(),
                'covers_all' => $covered === $items->count(),
                'covered_items' => $covered,
                'total_items' => $items->count(),
                'coverage_percentage' => (int) round(($covered / $total) * 100),
                'is_current' => $warehouse->id === (int) $order->fulfillment_warehouse_id,
            ];
        });

        $recommended = $this->pickWarehouse($order, $order->fulfillment_type, $rows);

        return [
            'warehouses' => $rows->map(fn ($row) => $row + [
                'is_recommended' => $row['warehouse_id'] === $recommended,
            ])->all(),
            'recommended_warehouse_id' => $recommended,
            'current_warehouse_id' => $order->fulfillment_warehouse_id ? (int) $order->fulfillment_warehouse_id : null,
        ];
    }

    /**
     * Chooses the warehouse to serve an order for a given fulfilment type.
     *
     * A pickup is collected in person, so it has to come from somewhere the
     * customer can walk into — a branch — even if a distribution centre holds
     * more stock. Shipping has no such constraint and simply goes to whichever
     * location can cover the most of the order.
     *
     * @param  Collection<int,array<string,mixed>>|null  $rows  precomputed coverage, to avoid recounting
     */
    public function pickWarehouse(SalesOrder $order, ?string $fulfillmentType = null, ?Collection $rows = null): ?int
    {
        $rows ??= collect($this->routingOptions($order)['warehouses']);

        if ($rows->isEmpty()) {
            return null;
        }

        $type = $fulfillmentType ?: $order->fulfillment_type;

        $preferredTypes = $type === SalesOrder::FULFILLMENT_PICKUP
            ? [Warehouse::TYPE_BRANCH]
            : [Warehouse::TYPE_DISTRIBUTION_CENTER, Warehouse::TYPE_WAREHOUSE];

        // The employee handling the order works out of one location; serving from
        // anywhere else means someone has to move the goods first.
        $employeeWarehouseId = $order->assignedEmployee?->warehouse_id;

        return $rows->sortByDesc(function (array $row) use ($preferredTypes, $employeeWarehouseId) {
            $score = 0;

            // Covering the whole order in one place beats every other factor:
            // a split shipment costs more than a slightly worse location.
            if ($row['covers_all']) {
                $score += 1000;
            }

            $score += $row['coverage_percentage'] * 2;

            if ($employeeWarehouseId && $row['warehouse_id'] === (int) $employeeWarehouseId) {
                $score += 120;
            }

            $typeIndex = array_search($row['location_type'], $preferredTypes, true);
            if ($typeIndex !== false) {
                $score += 80 - ($typeIndex * 20);
            }

            return $score;
        })->first()['warehouse_id'] ?? null;
    }

    /* ------------------------------------------------------------------ *
     * Stage moves
     * ------------------------------------------------------------------ */

    /**
     * Moves an order to a stage and performs whatever that stage means
     * commercially. The whole move is one transaction: either the status, the
     * stock and the ledger all move, or none of them do.
     *
     * @return array<string,mixed>  what the move actually changed
     */
    public function transitionTo(SalesOrder $order, string $target, array $options = []): array
    {
        return DB::transaction(function () use ($order, $target, $options) {
            // Lock the order first: two operators clicking "ship" at the same
            // moment would otherwise both pass the status check below.
            $order = SalesOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load('items.product');

            $current = (string) $order->status;

            if ($current === $target) {
                return ['changed' => false, 'status' => $current, 'effects' => []];
            }

            $allowed = self::TRANSITIONS[$current] ?? [];
            if (!in_array($target, $allowed, true)) {
                throw new RuntimeException(sprintf(
                    'لا يمكن نقل الطلب من "%s" إلى "%s". الانتقالات المسموحة: %s.',
                    $this->statusLabel($current),
                    $this->statusLabel($target),
                    $allowed ? implode('، ', array_map([$this, 'statusLabel'], $allowed)) : 'لا يوجد'
                ));
            }

            // Cancelling reverses stock and posts reversing entries; a record of
            // that with no stated reason is not much of a record.
            if ($target === SalesOrder::STATUS_CANCELLED && trim((string) ($options['note'] ?? '')) === '') {
                throw new RuntimeException('يرجى ذكر سبب الإلغاء — يُحفظ في سجل مراحل الطلب.');
            }

            $effects = match ($target) {
                SalesOrder::STATUS_CONFIRMED => $this->applyConfirmation($order),
                SalesOrder::STATUS_PROCESSING => $this->applyProcessing($order),
                SalesOrder::STATUS_SHIPPED => $this->applyShipment($order, $options),
                SalesOrder::STATUS_DELIVERED => $this->applyDelivery($order, $options),
                SalesOrder::STATUS_CANCELLED => $this->applyCancellation($order, $current),
                default => [],
            };

            // Handing the order to whoever owns the next stage. Routing it with
            // the move keeps "who is doing this now" attached to the stage that
            // needs doing, rather than to whoever happened to raise the order.
            if (!empty($options['assigned_employee_id'])) {
                $order->assigned_employee_id = (int) $options['assigned_employee_id'];
                $effects['assigned_employee_id'] = $order->assigned_employee_id;
            }

            $order->status = $target;
            $order->save();

            $this->recordHistory($order, $current, $target, $options['note'] ?? null, $effects);

            return [
                'changed' => true,
                'from' => $current,
                'status' => $target,
                'effects' => $effects,
            ];
        });
    }

    /**
     * Confirmation: the sale is committed.
     *
     * Stock is held rather than removed — the goods are still on the shelf until
     * someone ships them — and the revenue side of the books is recorded, since
     * the customer now owes us for the order.
     */
    private function applyConfirmation(SalesOrder $order): array
    {
        $this->assertHasItems($order);

        $warehouseId = $order->fulfillment_warehouse_id ?: $this->pickWarehouse($order);
        if (!$warehouseId) {
            throw new RuntimeException('لا يوجد مستودع نشط يمكن توجيه الطلب إليه.');
        }

        $order->fulfillment_warehouse_id = $warehouseId;

        $this->assertCanCover($order, (int) $warehouseId);
        $this->reserveAll($order, (int) $warehouseId);

        // Whether this confirmation is what raised the invoice decides whether
        // it is also what should charge the customer. Orders that arrive with an
        // invoice already attached — a customer purchase request builds one up
        // front — had the receivable applied when that invoice was created, and
        // charging again here billed them twice for the same goods.
        $invoiceExisted = $this->existingInvoice($order) !== null;

        $invoice = $this->ensureInvoice($order);

        $this->ledger->postInvoice($invoice);

        if (!$invoiceExisted) {
            $order->customer?->updateBalance((float) $order->total);
        }

        $order->confirmed_at = now();

        $effects = [
            'reserved_warehouse_id' => (int) $warehouseId,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'journal_posted' => true,
        ];

        // Confirming is the moment the warehouses are told to go and get the
        // goods, so the picking lists are raised here rather than being a
        // separate thing somebody has to remember. One per warehouse the order
        // is routed to, each naming only its own share — an order split across
        // two buildings is two picking jobs, not one.
        //
        // Guarded: a picking problem — no bins mapped, a warehouse
        // misconfigured — must not undo a committed sale, and the missing lists
        // are reported instead.
        try {
            $lists = $this->picking->createPickingListsForPlan(
                $order,
                $this->pickingPlanFor($order, (int) $warehouseId)
            );

            $effects['picking_lists'] = collect($lists)
                ->map(fn ($list, $whId) => [
                    'warehouse_id' => (int) $whId,
                    'list_number' => $list->list_number,
                ])->values()->all();
        } catch (\Throwable $e) {
            $effects['picking_list_error'] = $e->getMessage();
            report($e);
        }

        return $effects;
    }

    /** Picking has started. Nothing financial happens; the reservation stands. */
    private function applyProcessing(SalesOrder $order): array
    {
        return ['reservation_held' => true];
    }

    /**
     * Shipment: the goods physically leave. This is the point where inventory
     * actually falls and the cost of the sale is recognised against the revenue
     * that was booked at confirmation.
     */
    private function applyShipment(SalesOrder $order, array $options): array
    {
        $warehouseId = (int) $order->fulfillment_warehouse_id;
        if (!$warehouseId) {
            throw new RuntimeException('لا يمكن شحن طلب بدون مستودع تنفيذ.');
        }

        // Settled before any stock moves. If the picking record contradicts what
        // is about to ship — a line found short on the shelf — this throws and
        // the whole transition rolls back, so nothing leaves the warehouse
        // against a count that was never confirmed. Picking itself moves no
        // stock; the issue below remains the only place inventory leaves.
        $pickingEffects = [];
        try {
            $picked = $this->picking->completeForShipment($order);
            if ($picked) {
                $pickingEffects['picking_list_number'] = $picked->list_number;
                $pickingEffects['picking_list_status'] = $picked->status;
            }
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }

        $movements = [];
        $cost = 0.0;

        // Cost accumulated per source warehouse, so the ledger can credit each
        // holding for exactly what left it.
        $costByWarehouse = [];

        $order->loadMissing('items.allocations');

        foreach ($order->items as $item) {
            $unitCost = (float) ($item->product->cost_price ?? 0);

            // A line may be filled from several places — part from the seller's
            // own stock, the rest from the main warehouse. Where a plan exists
            // it decides the sources; a line without one ships whole from the
            // order's fulfilment warehouse, which is the single-source case.
            $sources = $this->shipmentSourcesFor($item, $warehouseId);

            foreach ($sources as $sourceWarehouseId => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                // Consumes the units held at confirmation rather than demanding
                // fresh availability, otherwise an order's own reservation would
                // block it from shipping.
                $movement = $this->inventory->shipReserved(
                    (int) $item->product_id,
                    (int) $quantity,
                    (int) $sourceWarehouseId,
                    [
                        // Keyed per source as well as per product: a split line
                        // writes one movement per warehouse, and sharing a key
                        // would make the second look like a repeat of the first
                        // and be skipped.
                        'key' => 'SO-' . $order->id . '-' . $item->product_id . '-W' . $sourceWarehouseId,
                        'reference' => 'sales_order',
                        'source' => $order->id,
                        'reason' => 'إخراج مخزون لطلب بيع رقم ' . $order->order_number,
                        'unit_cost' => $unitCost,
                    ]
                );

                if ($movement) {
                    $movements[] = $movement->id;
                }

                // The movement carries what the units actually cost, drawn from
                // the FIFO layers as they were consumed. Using the product's
                // current cost price instead valued a batch bought at 20 and a
                // batch bought at 30 identically, and the margin was wrong by
                // the difference on every unit.
                $lineCost = $movement
                    ? (float) $movement->total_cost
                    : $unitCost * (int) $quantity;

                $cost += $lineCost;
                $costByWarehouse[(int) $sourceWarehouseId] =
                    ($costByWarehouse[(int) $sourceWarehouseId] ?? 0) + $lineCost;
            }
        }

        $this->ledger->postCostOfGoodsSoldBySource(
            key: 'so_cogs:' . $order->id,
            costByWarehouse: $costByWarehouse,
            label: 'طلب بيع ' . $order->order_number,
            reference: $order,
            currency: $order->currency,
        );

        $order->shipped_at = now();

        if (!empty($options['tracking_number'])) {
            $order->tracking_number = $options['tracking_number'];
        }
        if (!empty($options['carrier'])) {
            $order->carrier = $options['carrier'];
        }

        $this->syncInvoiceStatus($order, Invoice::STATUS_SHIPPED);

        return $pickingEffects + [
            'stock_movements' => $movements,
            'cost_of_goods_sold' => round($cost, 2),
        ];
    }

    /**
     * The order's routing turned inside out: what each warehouse must pick.
     *
     * `shipmentSourcesFor` answers per line ("this line comes from here and
     * there"); a picking list is per building ("fetch these lines off your
     * shelves"), so the same plan is needed the other way round.
     *
     * @return array<int,array<int,int>>  warehouse id => [order item id => quantity]
     */
    private function pickingPlanFor(SalesOrder $order, int $fallbackWarehouseId): array
    {
        $order->loadMissing('items.allocations');

        $plan = [];

        foreach ($order->items as $item) {
            foreach ($this->shipmentSourcesFor($item, $fallbackWarehouseId) as $warehouseId => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $plan[(int) $warehouseId][$item->id] =
                    ($plan[(int) $warehouseId][$item->id] ?? 0) + (int) $quantity;
            }
        }

        return $plan;
    }

    /**
     * Which warehouses a line ships from, and how much comes out of each.
     *
     * Returns `[warehouse_id => quantity]`. Three shapes come out of this, and
     * they are the three ways an order gets filled:
     *
     *   - no plan            the whole line leaves the order's warehouse
     *   - a single-row plan  the whole line leaves that one warehouse
     *   - a multi-row plan   the line is split across its sources
     *
     * A plan that does not add up to the line's quantity has the remainder
     * taken from the order's own warehouse, so a stale or partial plan can
     * never ship fewer goods than the customer is being charged for.
     *
     * @return array<int,int>
     */
    private function shipmentSourcesFor($item, int $fallbackWarehouseId): array
    {
        $allocations = $item->allocations ?? collect();

        if ($allocations->isEmpty()) {
            return [$fallbackWarehouseId => (int) $item->quantity];
        }

        $sources = [];
        foreach ($allocations as $allocation) {
            $quantity = (int) $allocation->quantity;
            if ($quantity <= 0) {
                continue;
            }

            $id = (int) $allocation->warehouse_id;
            $sources[$id] = ($sources[$id] ?? 0) + $quantity;
        }

        $planned = array_sum($sources);
        $shortfall = (int) $item->quantity - $planned;

        if ($shortfall > 0) {
            $sources[$fallbackWarehouseId] = ($sources[$fallbackWarehouseId] ?? 0) + $shortfall;
        }

        return $sources;
    }

    /**
     * Delivery — the goods reached the customer, and typically the money comes
     * back the other way.
     *
     * Settlement is offered here rather than assumed: a driver collecting cash
     * at the door and a credit customer paying next month are both ordinary,
     * and booking a payment that was never handed over would be worse than
     * leaving the invoice open. So the caller says whether money changed hands,
     * and how much.
     *
     * The collection itself goes through PaymentRecorder — the same path the
     * payments screen uses — so the invoice, the customer balance and the ledger
     * move together no matter which door the cash came through.
     */
    private function applyDelivery(SalesOrder $order, array $options): array
    {
        $order->delivered_at = now();
        $order->actual_delivery_date = now();

        $effects = ['delivered_at' => $order->delivered_at->toDateTimeString()];

        if (empty($options['settle'])) {
            return $effects;
        }

        $invoice = $this->existingInvoice($order);
        if (!$invoice) {
            throw new RuntimeException('لا توجد فاتورة لتسويتها على هذا الطلب.');
        }

        $outstanding = $this->payments->outstanding($invoice);
        if ($outstanding <= 0.009) {
            $effects['settlement'] = ['already_settled' => true];

            return $effects;
        }

        // Defaults to clearing the invoice, which is what "collected on
        // delivery" almost always means; a partial amount is honoured as given.
        $amount = isset($options['settlement_amount'])
            ? round((float) $options['settlement_amount'], 2)
            : $outstanding;

        $payment = $this->payments->record($invoice, $amount, [
            'method' => $options['payment_method'] ?? null,
            'reference' => $options['payment_reference'] ?? null,
            'notes' => 'تحصيل عند تسليم الطلب ' . $order->order_number,
            'sales_order_id' => $order->id,
        ]);

        $effects['settlement'] = [
            'payment_number' => $payment->payment_number,
            'amount' => (float) $payment->amount,
            'method' => $payment->payment_method,
            'remaining' => $this->payments->outstanding($invoice->refresh()),
        ];

        return $effects;
    }

    /**
     * Cancellation unwinds whatever the order had already done, without
     * rewriting history: the ledger gets reversing entries, not deletions.
     */
    private function applyCancellation(SalesOrder $order, string $from): array
    {
        $effects = [];
        $warehouseId = (int) $order->fulfillment_warehouse_id;

        if (in_array($from, self::SHIPPED_STAGES, true)) {
            // The goods are already out. Bring them back to the shelves they
            // actually left, and reverse the cost.
            //
            // Returning everything to the order's own warehouse — which is what
            // this did — put a split order's whole quantity in one building: the
            // fulfilment warehouse gained units it never shipped and the other
            // lost them for good. The ledger reversal credits each holding
            // correctly, so the books and the shelves stopped agreeing.
            $order->loadMissing('items.allocations');

            $returned = [];

            foreach ($order->items as $item) {
                foreach ($this->shipmentSourcesFor($item, $warehouseId) as $sourceId => $quantity) {
                    if ($quantity <= 0) {
                        continue;
                    }

                    $this->inventory->receive(
                        (int) $item->product_id,
                        (int) $quantity,
                        (int) $sourceId,
                        [
                            // Keyed per source as well as per product, matching
                            // the shipment: one return per warehouse, and a
                            // shared key would make the second look like a
                            // repeat of the first and be skipped.
                            'key' => 'SO-CANCEL-' . $order->id . '-' . $item->product_id . '-W' . $sourceId,
                            'reference' => 'sales_order_cancelled',
                            'source' => $order->id,
                            'reason' => 'إرجاع مخزون لإلغاء طلب بيع رقم ' . $order->order_number,
                            'unit_cost' => (float) ($item->product?->cost_price ?? 0),
                        ]
                    );

                    $returned[(int) $sourceId] = ($returned[(int) $sourceId] ?? 0) + (int) $quantity;
                }
            }

            $this->ledger->reverseFor('so_cogs:' . $order->id);
            $effects['stock_returned'] = true;
            $effects['returned_by_warehouse'] = $returned;
        } elseif (in_array($from, self::RESERVED_STAGES, true)) {
            // Still on the shelf, just held for this order — let it go.
            $this->releaseAll($order, $warehouseId);
            $effects['reservation_released'] = true;
        }

        // A pending picking list would send someone to the shelf for a sale
        // that is off.
        if ($cancelledList = $this->picking->cancelForOrder($order)) {
            $effects['picking_list_cancelled'] = $cancelledList->list_number;
        }

        // The receivable was raised when the invoice was, so the live invoice —
        // not a timestamp — is what says whether there is anything to reverse.
        $invoice = $this->existingInvoice($order);
        if ($invoice) {
            $this->ledger->reverseFor('invoice:' . $invoice->id);
            $invoice->update(['status' => Invoice::STATUS_CANCELLED, 'paid_at' => null]);
            $effects['invoice_cancelled'] = $invoice->invoice_number;

            $order->customer?->updateBalance(-1 * (float) $invoice->total);
            $effects['customer_balance_reversed'] = round((float) $invoice->total, 2);
        }

        return $effects;
    }

    /* ------------------------------------------------------------------ *
     * Fulfilment type
     * ------------------------------------------------------------------ */

    /**
     * Changes how an order will be fulfilled, and moves everything that follows
     * from that: which warehouse serves it, what the customer is charged for
     * delivery, and the invoice and ledger behind that charge.
     *
     * Refused once the goods have shipped — at that point the delivery method is
     * a fact, not a plan, and re-routing would describe a shipment that did not
     * happen.
     */
    public function changeFulfillmentType(SalesOrder $order, string $type, array $options = []): array
    {
        $allowedTypes = [SalesOrder::FULFILLMENT_SHIP, SalesOrder::FULFILLMENT_PICKUP, SalesOrder::FULFILLMENT_DELIVERY];

        if (!in_array($type, $allowedTypes, true)) {
            throw new RuntimeException('نوع تنفيذ غير معروف: ' . $type);
        }

        return DB::transaction(function () use ($order, $type, $options) {
            $order = SalesOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load('items.product');

            if (in_array((string) $order->status, self::SHIPPED_STAGES, true)) {
                throw new RuntimeException(
                    'لا يمكن تغيير نوع التنفيذ بعد شحن الطلب — البضاعة غادرت المستودع فعلاً.'
                );
            }

            if ($order->status === SalesOrder::STATUS_CANCELLED) {
                throw new RuntimeException('لا يمكن تغيير نوع التنفيذ لطلب ملغي.');
            }

            $previousType = $order->fulfillment_type;
            $previousWarehouseId = (int) $order->fulfillment_warehouse_id;

            $order->fulfillment_type = $type;

            // Re-route. An explicit warehouse from the operator wins over the
            // recommendation — they may know something the stock figures do not.
            $targetWarehouseId = (int) ($options['fulfillment_warehouse_id'] ?? 0)
                ?: (int) $this->pickWarehouse($order, $type);

            if (!$targetWarehouseId) {
                throw new RuntimeException('لا يوجد مستودع نشط يمكن توجيه الطلب إليه.');
            }

            $effects = ['from_type' => $previousType, 'to_type' => $type];

            // A confirmed order is holding stock somewhere. Moving it means
            // moving the hold too, and only after checking the new location can
            // honour it — releasing first would risk losing the units to
            // another order if the new warehouse turns out to be short.
            if ($this->holdsReservation($order) && $targetWarehouseId !== $previousWarehouseId) {
                $this->assertCanCover($order, $targetWarehouseId);
                $this->reserveAll($order, $targetWarehouseId);
                $this->releaseAll($order, $previousWarehouseId);
                $effects['reservation_moved'] = [
                    'from_warehouse_id' => $previousWarehouseId,
                    'to_warehouse_id' => $targetWarehouseId,
                ];
            }

            $order->fulfillment_warehouse_id = $targetWarehouseId;
            $effects['warehouse_id'] = $targetWarehouseId;

            // Collecting in person costs the customer no delivery fee. Going the
            // other way, the operator supplies the fee — the system has no rate
            // card to invent one from.
            $newShipping = $type === SalesOrder::FULFILLMENT_PICKUP
                ? 0.0
                : round((float) ($options['shipping_cost'] ?? $order->shipping_cost ?? 0), 2);

            if (round((float) $order->shipping_cost, 2) !== $newShipping) {
                $effects['shipping_cost'] = ['from' => round((float) $order->shipping_cost, 2), 'to' => $newShipping];
                $order->shipping_cost = $newShipping;
                $this->recalculateTotals($order);
                $effects['total'] = round((float) $order->total, 2);
            }

            $order->save();

            // The invoice was raised off the old figures. Rather than editing a
            // posted document, the old entry is reversed and the corrected
            // invoice re-posted, so the audit trail shows both.
            if (isset($effects['shipping_cost']) && $invoice = $this->existingInvoice($order)) {
                $balanceDelta = $this->restateInvoice($order, $invoice);
                $effects['invoice_restated'] = $invoice->invoice_number;
                $effects['customer_balance_delta'] = $balanceDelta;
            }

            return $effects;
        });
    }

    /* ------------------------------------------------------------------ *
     * Invoicing
     * ------------------------------------------------------------------ */

    /**
     * The order's invoice, creating it if this is the first time. One live
     * invoice per order: the old code had two independent creation paths and
     * neither checked, so an order could carry several.
     */
    public function ensureInvoice(SalesOrder $order): Invoice
    {
        $existing = $this->existingInvoice($order);
        if ($existing) {
            return $existing;
        }

        $order->loadMissing('items.product', 'customer');

        $invoice = Invoice::create([
            'invoice_number' => $this->nextInvoiceNumber(),
            'customer_id' => $order->customer_id,
            'sales_order_id' => $order->id,
            'subtotal' => $order->subtotal,
            'tax' => $order->tax,
            'discount' => $order->discount,
            'additional_charges' => $order->shipping_cost ?? 0,
            'total' => $order->total,
            'paid_amount' => 0,
            'due_amount' => $order->total,
            'status' => Invoice::STATUS_CONFIRMED,
            'notes' => $order->notes,
            'created_by' => auth()->id(),
            'currency' => $order->currency ?: 'SAR',
        ]);

        foreach ($order->items as $item) {
            // `invoice_items` stores `product_name` (NOT NULL), `tax_amount` and
            // `total_price`. The previous code wrote `description`, `tax` and
            // `total` — none of which are columns on this table — so the insert
            // failed on the missing product_name and took the whole confirmation
            // down with it. That is why no order had ever reached the ledger.
            $invoice->items()->create([
                'product_id' => $item->product_id,
                'product_name' => $item->product->name_ar ?? $item->product->name_en ?? $item->product->name ?? ('#' . $item->product_id),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount ?? 0,
                'tax_amount' => $item->tax ?? 0,
            ]);
        }

        return $invoice;
    }

    /** The order's live invoice, if it has one. A cancelled one does not count. */
    public function existingInvoice(SalesOrder $order): ?Invoice
    {
        return Invoice::where('sales_order_id', $order->id)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->latest('id')
            ->first();
    }

    /**
     * Rewrites an invoice to match the order it came from and re-posts it.
     *
     * @return float  how much the customer's balance moved
     */
    private function restateInvoice(SalesOrder $order, Invoice $invoice): float
    {
        $previousTotal = (float) $invoice->total;

        $this->ledger->reverseFor('invoice:' . $invoice->id);

        $invoice->update([
            'subtotal' => $order->subtotal,
            'tax' => $order->tax,
            'discount' => $order->discount,
            'additional_charges' => $order->shipping_cost ?? 0,
            'total' => $order->total,
            'due_amount' => max(0, (float) $order->total - (float) $invoice->paid_amount),
        ]);

        // `post()` short-circuits on a posting key it has already seen, and the
        // original key is now attached to a reversed entry — so the restatement
        // needs a key of its own.
        $this->ledger->post(
            key: 'invoice:' . $invoice->id . ':restated:' . $order->updated_at?->getTimestamp(),
            date: now()->toDateString(),
            description: 'إعادة إثبات فاتورة ' . $invoice->invoice_number . ' بعد تغيير نوع التنفيذ',
            lines: $this->invoiceLines($invoice),
            reference: $invoice,
            module: 'sales',
            currency: $invoice->currency,
        );

        $delta = round((float) $order->total - $previousTotal, 2);
        if (abs($delta) > 0.005) {
            $order->customer?->updateBalance($delta);
        }

        return $delta;
    }

    /** The revenue-side lines for an invoice, matching LedgerPostingService::postInvoice. */
    private function invoiceLines(Invoice $invoice): array
    {
        $total = round((float) $invoice->total, 2);
        $tax = round((float) $invoice->tax, 2);
        $charges = round((float) ($invoice->additional_charges ?? 0), 2);
        $goods = round($total - $tax - $charges, 2);

        $label = 'فاتورة ' . $invoice->invoice_number;

        $lines = [['role' => 'accounts_receivable', 'debit' => $total, 'description' => 'ذمم مدينة - ' . $label]];

        if ($goods > 0) {
            $lines[] = ['role' => 'sales_revenue', 'credit' => $goods, 'description' => 'إيراد مبيعات - ' . $label];
        }
        if ($charges > 0) {
            $lines[] = ['role' => 'additional_charges_revenue', 'credit' => $charges, 'description' => 'إيراد شحن وخدمات - ' . $label];
        }
        if ($tax > 0) {
            $lines[] = ['role' => 'tax_payable', 'credit' => $tax, 'description' => 'ضريبة مستحقة - ' . $label];
        }

        return $lines;
    }

    /**
     * Invoice numbering. The old `Invoice::count() + 1` reused a number as soon
     * as any invoice was deleted, and two simultaneous requests always collided.
     * Derived from the last id instead, under a lock.
     */
    private function nextInvoiceNumber(): string
    {
        $lastId = (int) (Invoice::orderByDesc('id')->lockForUpdate()->value('id') ?? 0);

        return 'INV-' . now()->format('Ymd') . '-' . str_pad((string) ($lastId + 1), 5, '0', STR_PAD_LEFT);
    }

    private function syncInvoiceStatus(SalesOrder $order, string $status): void
    {
        $invoice = $this->existingInvoice($order);

        // Never downgrade an invoice that a payment has already settled.
        if ($invoice && $invoice->status !== Invoice::STATUS_DELIVERED) {
            $invoice->update(['status' => $status]);
        }
    }

    /* ------------------------------------------------------------------ *
     * Stock helpers
     * ------------------------------------------------------------------ */

    private function reserveAll(SalesOrder $order, int $warehouseId): void
    {
        $order->loadMissing('items.allocations');

        foreach ($order->items as $item) {
            // Held where the goods will actually be taken from. Reserving the
            // whole line at one warehouse while shipping it from two left the
            // first over-held and the second unprotected, so a split line's
            // second half could be sold out from under it before it shipped.
            foreach ($this->shipmentSourcesFor($item, $warehouseId) as $sourceId => $quantity) {
                $ok = $this->inventory->reserve((int) $item->product_id, (int) $quantity, (int) $sourceId);

                if (!$ok) {
                    throw new RuntimeException(sprintf(
                        'تعذّر حجز %d من "%s" في مستودع %s — الكمية المتاحة تغيّرت.',
                        $quantity,
                        $item->product->name_ar ?? $item->product->name ?? ('#' . $item->product_id),
                        Warehouse::find($sourceId)?->name ?? ('#' . $sourceId)
                    ));
                }
            }
        }
    }

    private function releaseAll(SalesOrder $order, ?int $warehouseId): void
    {
        if (!$warehouseId) {
            return;
        }

        $order->loadMissing('items.allocations');

        // Released from wherever it was held. Releasing the whole line from one
        // warehouse would leave a split line's other source held for an order
        // that no longer exists.
        foreach ($order->items as $item) {
            foreach ($this->shipmentSourcesFor($item, $warehouseId) as $sourceId => $quantity) {
                $this->inventory->release((int) $item->product_id, (int) $quantity, (int) $sourceId);
            }
        }
    }

    /**
     * Refuses the move unless every line can be served from the place it is
     * planned to come from, naming the products that fall short rather than
     * failing on the first one.
     *
     * Checked per source, not against the order's own warehouse. A line topped
     * up from the main warehouse — the field app's shortage flow — is held and
     * shipped from there, so measuring it against the branch reported a
     * shortfall that did not exist and made such an order impossible to confirm.
     * This mirrors reserveAll(), which was already source-aware; the check and
     * the act it guards now ask the same question.
     */
    private function assertCanCover(SalesOrder $order, int $warehouseId): void
    {
        $order->loadMissing('items.allocations');

        $short = [];

        foreach ($order->items as $item) {
            foreach ($this->shipmentSourcesFor($item, $warehouseId) as $sourceId => $required) {
                $available = $this->sellableFor((int) $item->product_id, (int) $sourceId, $order);

                if ($available < $required) {
                    $short[] = sprintf(
                        '%s في مستودع "%s" (مطلوب %d، متاح %d)',
                        $item->product->name_ar ?? $item->product->name ?? ('#' . $item->product_id),
                        Warehouse::find($sourceId)?->name ?? ('#' . $sourceId),
                        $required,
                        $available
                    );
                }
            }
        }

        if ($short) {
            throw new RuntimeException('الرصيد غير كافٍ: ' . implode('، ', $short) . '.');
        }
    }

    /**
     * Units of a product this order could take from a warehouse.
     *
     * An order that already holds a reservation must not be told it is short by
     * its own hold — that would make re-routing or re-checking a confirmed order
     * impossible — so its own reserved units are counted back in.
     */
    private function sellableFor(int $productId, int $warehouseId, SalesOrder $order): int
    {
        $row = WarehouseInventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$row) {
            return 0;
        }

        $sellable = max(0, (int) $row->available_quantity - (int) $row->reserved_quantity);

        $ownHold = $this->ownHoldAt($order, $productId, $warehouseId);
        if ($ownHold > 0) {
            $sellable += min($ownHold, (int) $row->reserved_quantity);
        }

        return $sellable;
    }

    /**
     * How much of a product this order is itself holding at one warehouse.
     *
     * Read from the sourcing plan rather than assumed to sit at the order's
     * fulfilment warehouse. A split order holds units in two places, and
     * crediting the whole line back at one of them told the caller a warehouse
     * had stock it does not — enough to pass a coverage check that the
     * reservation would then refuse.
     */
    private function ownHoldAt(SalesOrder $order, int $productId, int $warehouseId): int
    {
        if (!$this->holdsReservation($order)) {
            return 0;
        }

        $fallback = (int) $order->fulfillment_warehouse_id;
        $held = 0;

        foreach ($order->items as $item) {
            if ((int) $item->product_id !== $productId) {
                continue;
            }

            $held += (int) ($this->shipmentSourcesFor($item, $fallback)[$warehouseId] ?? 0);
        }

        return $held;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Whether the order is currently holding stock.
     *
     * Keyed on the status, not on `confirmed_at`. Orders confirmed by the old
     * code never got that timestamp, so trusting it let a legacy order skip the
     * availability check entirely and re-route to a warehouse holding none of
     * the goods.
     */
    private function holdsReservation(SalesOrder $order): bool
    {
        return in_array((string) $order->status, self::RESERVED_STAGES, true);
    }

    private function assertHasItems(SalesOrder $order): void
    {
        if ($order->items->isEmpty()) {
            throw new RuntimeException('لا يمكن تأكيد طلب بلا أصناف.');
        }
    }

    /**
     * Keeps the order total in step with its parts. Delivery charged to the
     * customer belongs in the total — it was previously stored on the order but
     * left out of it, so the invoice under-billed every shipped order.
     */
    private function recalculateTotals(SalesOrder $order): void
    {
        $subtotal = $order->items->sum(
            fn ($item) => ((float) $item->unit_price * (int) $item->quantity)
                - (float) ($item->discount ?? 0)
                + (float) ($item->tax ?? 0)
        );

        $order->subtotal = round($subtotal, 2);
        $order->total = round(
            $subtotal - (float) ($order->discount ?? 0) + (float) ($order->tax ?? 0) + (float) ($order->shipping_cost ?? 0),
            2
        );
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            SalesOrder::STATUS_PENDING => 'معلق',
            SalesOrder::STATUS_CONFIRMED => 'مؤكد',
            SalesOrder::STATUS_PROCESSING => 'قيد المعالجة',
            SalesOrder::STATUS_SHIPPED => 'تم الشحن',
            SalesOrder::STATUS_DELIVERED => 'تم التسليم',
            SalesOrder::STATUS_CANCELLED => 'ملغي',
            default => $status,
        };
    }

    /* ------------------------------------------------------------------ *
     * Source selection
     * ------------------------------------------------------------------ */

    /**
     * The per-line sourcing plan, with what each warehouse could actually give.
     *
     * This is what the operator chooses from: for every line, every warehouse
     * that holds any of it, how much is free there, and how much the current
     * plan takes. Without it the split is invisible — the order simply ships
     * from wherever the plan happens to say.
     *
     * @return array<string,mixed>
     */
    public function sourcingPlan(SalesOrder $order): array
    {
        $order->loadMissing('items.product', 'items.allocations');

        $warehouses = Warehouse::where('is_active', true)->orderByDesc('is_primary')->orderBy('id')->get();
        $fallback = (int) $order->fulfillment_warehouse_id;

        $lines = $order->items->map(function ($item) use ($warehouses, $order, $fallback) {
            $planned = $this->shipmentSourcesFor($item, $fallback);

            $sources = $warehouses->map(function (Warehouse $w) use ($item, $order, $planned) {
                return [
                    'warehouse_id' => $w->id,
                    'warehouse_name' => $w->name,
                    'is_primary' => (bool) $w->is_primary,
                    'available' => $this->sellableFor((int) $item->product_id, $w->id, $order),
                    'allocated' => (int) ($planned[$w->id] ?? 0),
                ];
            })->values();

            $allocated = (int) array_sum($planned);

            return [
                'item_id' => $item->id,
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product->name_ar ?? $item->product->name_en ?? ('#' . $item->product_id),
                'sku' => $item->product->sku,
                'quantity' => (int) $item->quantity,
                'allocated' => $allocated,
                'remaining' => max(0, (int) $item->quantity - $allocated),
                // A plan is only complete when every unit has a source named.
                'is_complete' => $allocated === (int) $item->quantity,
                'has_explicit_plan' => $item->allocations->isNotEmpty(),
                'sources' => $sources,
            ];
        })->values();

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'fulfillment_warehouse_id' => $fallback,
            'editable' => $this->sourcingEditable($order),
            'lines' => $lines,
        ];
    }

    /** Sourcing may be changed until the goods leave; after that it is history. */
    public function sourcingEditable(SalesOrder $order): bool
    {
        return !in_array((string) $order->status, [
            SalesOrder::STATUS_SHIPPED,
            SalesOrder::STATUS_DELIVERED,
            SalesOrder::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Replaces the sourcing plan, moving any stock already held to match.
     *
     * @param  array<int,array<int,int>>  $plan  item id => [warehouse id => quantity]
     *
     * @throws RuntimeException
     */
    public function saveSourcingPlan(SalesOrder $order, array $plan): array
    {
        return DB::transaction(function () use ($order, $plan) {
            $order = SalesOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load('items.product', 'items.allocations');

            if (!$this->sourcingEditable($order)) {
                throw new RuntimeException(
                    'لا يمكن تغيير مصدر البضاعة بعد شحن الطلب — ما خرج من المستودعات واقعة لا تُعدَّل.'
                );
            }

            $held = $this->holdsReservation($order);
            $fallback = (int) $order->fulfillment_warehouse_id;

            // Everything currently held goes back first, so the new plan is
            // checked against true availability rather than against stock this
            // same order is sitting on.
            if ($held) {
                $this->releaseAll($order, $fallback);
            }

            foreach ($order->items as $item) {
                $requested = $plan[$item->id] ?? null;
                if ($requested === null) {
                    continue;
                }

                $total = array_sum($requested);
                if ($total !== (int) $item->quantity) {
                    throw new RuntimeException(sprintf(
                        'مجموع مصادر "%s" هو %d بينما الكمية المطلوبة %d.',
                        $item->product->name_ar ?? $item->product->name ?? ('#' . $item->product_id),
                        $total,
                        $item->quantity
                    ));
                }

                $item->allocations()->delete();

                foreach ($requested as $warehouseId => $quantity) {
                    if ((int) $quantity <= 0) {
                        continue;
                    }

                    $item->allocations()->create([
                        'warehouse_id' => (int) $warehouseId,
                        'quantity' => (int) $quantity,
                        'status' => 'pending',
                    ]);
                }
            }

            $order->load('items.allocations');

            // Re-taken against the new plan. A source that cannot cover its
            // share fails here, with the whole change rolled back — the old
            // plan and its holds survive intact rather than being half-applied.
            if ($held) {
                $this->reserveAll($order, $fallback);
            }

            return $this->sourcingPlan($order->refresh());
        });
    }

    /* ------------------------------------------------------------------ *
     * Stage history and follow-up
     * ------------------------------------------------------------------ */

    /** Appends one row to the order's stage history. Never updates an existing one. */
    private function recordHistory(SalesOrder $order, ?string $from, string $to, ?string $note, array $effects): void
    {
        SalesOrderStatusHistory::create([
            'sales_order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note ? trim($note) : null,
            // Only the summary keys; the documents themselves are the record.
            'effects' => $effects ?: null,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * How the order is doing against the clock.
     *
     * Status alone says where an order is, never how long it has been stuck
     * there — so an order confirmed three weeks ago and never picked looked
     * exactly like one confirmed this morning. These are the figures a follow-up
     * screen needs to tell them apart.
     *
     * @return array<string,mixed>
     */
    public function followUp(SalesOrder $order): array
    {
        $status = (string) $order->status;
        $isOpen = !in_array($status, [SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_CANCELLED], true);

        // When the order last moved. Falls back to when it was raised, so an
        // order that has never moved still ages from a real date.
        $since = SalesOrderStatusHistory::where('sales_order_id', $order->id)
            ->latest('id')
            ->value('created_at') ?? $order->created_at;

        $daysInStage = $since ? (int) $since->startOfDay()->diffInDays(now()->startOfDay()) : 0;

        $expected = $order->expected_delivery;
        $isOverdue = $isOpen && $expected && $expected->isBefore(now()->startOfDay());
        $daysOverdue = $isOverdue ? (int) $expected->startOfDay()->diffInDays(now()->startOfDay()) : 0;

        // What counts as "too long" differs by stage: a pending order is waiting
        // on a decision, a shipped one on a courier.
        $threshold = match ($status) {
            SalesOrder::STATUS_PENDING => 2,
            SalesOrder::STATUS_CONFIRMED => 3,
            SalesOrder::STATUS_PROCESSING => 2,
            SalesOrder::STATUS_SHIPPED => 7,
            default => null,
        };

        $isStalled = $threshold !== null && $daysInStage > $threshold;

        $reasons = [];
        if ($isOverdue) {
            $reasons[] = 'تجاوز موعد التسليم المتوقع بـ ' . $daysOverdue . ' يوم';
        }
        if ($isStalled) {
            $reasons[] = 'متوقف في مرحلة "' . $this->statusLabel($status) . '" منذ ' . $daysInStage . ' يوم';
        }

        return [
            'days_in_stage' => $daysInStage,
            'stage_since' => $since?->toDateTimeString(),
            'is_open' => $isOpen,
            'is_overdue' => $isOverdue,
            'days_overdue' => $daysOverdue,
            'is_stalled' => $isStalled,
            'stage_threshold_days' => $threshold,
            'needs_attention' => $isOverdue || $isStalled,
            'attention_reasons' => $reasons,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Diagnostics
     * ------------------------------------------------------------------ */

    /**
     * What is inconsistent about this order right now.
     *
     * An order can look complete on screen — delivered, invoiced, stock gone —
     * while the ledger never received the revenue behind it. Nothing surfaced
     * that: the figures each looked right on their own screen and only
     * disagreed when someone read the income statement. These checks compare
     * the order against the three records that are supposed to follow it, and
     * say plainly which one is missing and what to do about it.
     *
     * @return array<int,array{level:string,code:string,title:string,detail:string,action:?string}>
     */
    public function diagnose(SalesOrder $order): array
    {
        $order->loadMissing('items.product', 'customer');

        $issues = [];
        $status = (string) $order->status;
        $isConfirmed = !in_array($status, [SalesOrder::STATUS_PENDING, SalesOrder::STATUS_CANCELLED], true);
        $hasShipped = in_array($status, self::SHIPPED_STAGES, true);

        $invoice = $this->existingInvoice($order);

        $add = function (string $level, string $code, string $title, string $detail, ?string $action = null) use (&$issues) {
            $issues[] = compact('level', 'code', 'title', 'detail', 'action');
        };

        /* ---- The invoice ---- */

        if ($isConfirmed && !$invoice) {
            $add('error', 'invoice_missing', 'لا توجد فاتورة لهذا الطلب',
                'الطلب مؤكد لكن لم تُنشأ له فاتورة مبيعات، فالإيراد غير مثبت والعميل غير مدين به.',
                'أعد تأكيد الطلب لإنشاء الفاتورة وترحيل قيدها.');
        }

        if ($invoice) {
            $posted = JournalEntryHeader::where('posting_key', 'invoice:' . $invoice->id)->exists();

            if (!$posted) {
                $add('error', 'invoice_not_posted', 'الفاتورة غير مُرحَّلة إلى دفتر الأستاذ',
                    'الفاتورة ' . $invoice->invoice_number . ' بقيمة ' . number_format((float) $invoice->total, 2)
                        . ' غير موجودة في القيود، فالإيراد لا يظهر في قائمة الدخل بينما تظهر تكلفته.',
                    'رحِّل الفاتورة إلى دفتر الأستاذ لتتطابق الإيرادات مع التكاليف.');
            }

            if (abs((float) $invoice->total - (float) $order->total) > 0.01) {
                $add('error', 'invoice_total_mismatch', 'إجمالي الفاتورة لا يطابق إجمالي الطلب',
                    'الطلب ' . number_format((float) $order->total, 2) . ' والفاتورة '
                        . number_format((float) $invoice->total, 2) . '.',
                    'راجع بنود الفاتورة أو أعد إثباتها من الطلب.');
            }
        }

        /* ---- The stock ---- */

        $movements = $this->movementsFor($order)->where('reference', 'sales_order');

        if ($hasShipped) {
            if ($movements->count() < $order->items->count()) {
                $add('error', 'movements_incomplete', 'حركات المخزون ناقصة',
                    'الطلب مشحون لكن عدد حركات الإخراج (' . $movements->count() . ') أقل من عدد الأصناف ('
                        . $order->items->count() . ').',
                    'راجع سجل الحركات المخزنية لهذا الطلب.');
            }

            $cogsPosted = JournalEntryHeader::where('posting_key', 'so_cogs:' . $order->id)->exists();
            if (!$cogsPosted) {
                $add('error', 'cogs_not_posted', 'تكلفة البضاعة المباعة غير مسجَّلة',
                    'خرجت البضاعة من المستودع دون قيد تكلفة، فمجمل الربح مبالغ فيه بمقدار تكلفة هذا الطلب.',
                    'تحقق من تسعير تكلفة المنتجات ثم أعد ترحيل قيد التكلفة.');
            }
        }

        // Cost price drives the COGS entry; a zero cost silently books the sale
        // at full margin.
        // Orphaned lines are excluded: they have no product to price, and the
        // check above already says so. Reading through a null product here also
        // emitted a warning per line on every diagnose() call.
        $unpriced = $order->items->filter(
            fn ($i) => $i->product !== null && (float) ($i->product->cost_price ?? 0) <= 0
        );

        if ($unpriced->isNotEmpty() && $isConfirmed) {
            $add('warning', 'cost_price_missing', 'أصناف بلا سعر تكلفة',
                $unpriced->count() . ' من أصناف الطلب لا يحمل سعر تكلفة، فتكلفة البضاعة المباعة تُحتسب صفراً لها ويظهر هامش الربح أعلى من حقيقته.',
                'أدخل سعر تكلفة هذه الأصناف: ' . $unpriced->take(3)
                    ->map(fn ($i) => $this->itemLabel($i))
                    ->implode('، ') . ($unpriced->count() > 3 ? ' وغيرها' : '') . '.');
        }

        // A confirmed order with no picking list means nobody has been told to
        // fetch the goods — the sale is committed but the warehouse never heard.
        //
        // Checked per routed warehouse, not once for the order: a split order
        // with a list at only one of its two sources looked perfectly served
        // while the other building had been told nothing.
        if ($this->holdsReservation($order)) {
            $instructed = $this->picking->activePickingLists($order)->pluck('warehouse_id')
                ->map(fn ($id) => (int) $id)->all();

            $uninstructed = collect(array_keys($this->pickingPlanFor($order, (int) $order->fulfillment_warehouse_id)))
                ->reject(fn ($warehouseId) => in_array((int) $warehouseId, $instructed, true))
                ->map(fn ($warehouseId) => Warehouse::find($warehouseId)?->name ?? ('#' . $warehouseId))
                ->values();

            if ($uninstructed->isNotEmpty()) {
                $add('warning', 'picking_list_missing', 'لا توجد قائمة تجهيز لهذا الطلب',
                    'الطلب مؤكد لكن لم تُنشأ قائمة تجهيز في ' . $uninstructed->implode('، ')
                        . '، فلن يصل ذلك المستودع أمر بسحب الأصناف من الرفوف.',
                    'أعد تأكيد الطلب لإنشاء القائمة، أو أنشئها يدوياً من شاشة WMS.');
            }
        }

        // Checked before the reservation, and reported instead of it for these
        // lines: a product that no longer exists is the *reason* nothing is
        // held, and leading with the symptom sent operators to re-route an
        // order that no warehouse could ever cover.
        $orphaned = $this->itemsWithMissingProduct($order);

        if ($orphaned->isNotEmpty()) {
            $add('error', 'product_missing', 'أصناف الطلب لم تعد موجودة في الكتالوج',
                $orphaned->count() . ' من بنود هذا الطلب تشير إلى منتجات محذوفة — غالباً بعد حذف الكتالوج '
                    . 'وإعادة استيراده بمعرّفات جديدة. البند بلا رصيد في أي مستودع، فلا يمكن حجزه ولا '
                    . 'تجهيزه ولا شحنه ولا احتساب تكلفته: ' . $orphaned->take(3)->implode('، ')
                    . ($orphaned->count() > 3 ? ' وغيرها' : '') . '.',
                'أعد ربط البنود بالمنتجات الحالية، أو ألغِ الطلب وأنشئه من جديد. '
                    . 'إعادة التأكيد أو تغيير المستودع لن تُجديا هنا.');
        }

        if ($this->holdsReservation($order)) {
            $unreserved = $this->itemsWithNoReservation($order);

            if ($unreserved->isNotEmpty()) {
                $add('warning', 'reservation_missing', 'أصناف بلا حجز في مستودع التنفيذ',
                    'الطلب مؤكد لكن لا توجد أي كمية محجوزة من ' . $unreserved->count()
                        . ' من أصنافه في المستودع الذي ستُسحب منه، فقد تُباع لطلب آخر قبل الشحن: '
                        . $unreserved->take(3)->implode('، ') . ($unreserved->count() > 3 ? ' وغيرها' : '') . '.',
                    'أعد توجيه الطلب إلى مستودع يغطيه، أو أعد تأكيده ليُعاد الحجز.');
            }
        }

        /* ---- The money ---- */

        if ($invoice) {
            $due = round((float) $invoice->total - (float) $invoice->paid_amount, 2);

            if ($due > 0.01 && $hasShipped) {
                $add('info', 'invoice_unpaid', 'الفاتورة غير محصَّلة',
                    'المتبقي على العميل ' . number_format($due, 2) . ' من أصل '
                        . number_format((float) $invoice->total, 2) . '.',
                    'سجّل دفعة من شاشة المدفوعات.');
            }
        }

        return $issues;
    }

    /**
     * Lines this order is holding nothing for, at the place it plans to take
     * them from.
     *
     * `warehouse_inventory.reserved_quantity` is a single aggregate with no link
     * back to the order that placed the hold, so "is *my* reservation intact?"
     * is not a question this schema can answer — comparing the order's quantity
     * against that total credits it with holds belonging to other orders and
     * reports covered when it is not.
     *
     * Only the one-directional inference is sound: a reservation of zero means
     * nothing is held for anybody, this order included. That yields no false
     * alarms, at the cost of staying silent on partial shortfalls — which is the
     * right trade for a panel whose value rests on being believed.
     *
     * Measured per planned source, not against the order's own warehouse. A line
     * topped up from the main warehouse is held *there*, and checking the branch
     * for it would report a missing reservation that is sitting safely one
     * warehouse over — the exact false alarm the paragraph above promises not to
     * raise.
     *
     * Lines whose product no longer exists are left out: they are reported by
     * their own check, which explains the real cause and gives advice that can
     * actually be followed.
     *
     * @return \Illuminate\Support\Collection<int,string>  product names
     */
    private function itemsWithNoReservation(SalesOrder $order)
    {
        $order->loadMissing('items.allocations');

        $fallback = (int) $order->fulfillment_warehouse_id;

        $live = $order->items->filter(fn ($i) => $i->product !== null);

        if (!$fallback) {
            return $live->map(fn ($i) => $this->itemLabel($i))->values();
        }

        $reserved = WarehouseInventory::whereIn('product_id', $live->pluck('product_id'))
            ->get(['warehouse_id', 'product_id', 'reserved_quantity'])
            ->groupBy('warehouse_id')
            ->map(fn ($rows) => $rows->pluck('reserved_quantity', 'product_id'));

        return $live
            ->filter(function ($item) use ($order, $fallback, $reserved) {
                foreach ($this->shipmentSourcesFor($item, $fallback) as $sourceId => $quantity) {
                    if ($quantity <= 0) {
                        continue;
                    }

                    // Held somewhere it is planned to come from is enough. The
                    // check is deliberately generous for the reason above.
                    if ((int) ($reserved[$sourceId][$item->product_id] ?? 0) > 0) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn ($i) => $this->itemLabel($i))
            ->values();
    }

    /**
     * Lines pointing at a product that is no longer in the catalogue.
     *
     * Deleting and re-importing the product list leaves older orders holding
     * ids that resolve to nothing. Such a line has no stock row anywhere, so it
     * cannot be reserved, picked, shipped or costed — and it read as an ordinary
     * missing reservation, which sent the operator off to re-route or re-confirm
     * an order that no warehouse on earth could cover.
     *
     * @return \Illuminate\Support\Collection<int,string>
     */
    private function itemsWithMissingProduct(SalesOrder $order)
    {
        return $order->items
            ->filter(fn ($i) => $i->product === null)
            ->map(fn ($i) => $i->description ?: ('#' . $i->product_id))
            ->values();
    }

    private function itemLabel($item): string
    {
        return $item->product->name_ar ?? $item->product->name ?? ('#' . $item->product_id);
    }

    /** The order's live picking list, for the detail screen. */
    public function pickingListFor(SalesOrder $order): ?\App\Models\PickingList
    {
        return $this->picking->activePickingList($order)?->load(['items.product:id,sku,name_ar,name_en', 'items.bin', 'picker:id,name', 'warehouse:id,name']);
    }

    /**
     * Every picking list the order has raised — one per warehouse it is routed
     * to. A split order is two picking jobs, and showing only one of them hides
     * half the work from whoever is chasing the order.
     */
    public function pickingListsFor(SalesOrder $order)
    {
        return $this->picking->activePickingLists($order)
            ->load(['items.product:id,sku,name_ar,name_en', 'items.bin', 'picker:id,name', 'warehouse:id,name']);
    }

    /** Stock movements this order has produced, for the detail screen. */
    public function movementsFor(SalesOrder $order)
    {
        return StockMovement::with('warehouse')
            ->whereIn('reference', ['sales_order', 'sales_order_cancelled'])
            ->where('source', $order->id)
            ->orderBy('id')
            ->get();
    }
}
