<?php

namespace App\Services\Inventory;

use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns what actually happens to a replenishment request as it moves.
 *
 * A branch asks the main warehouse for stock. Nothing is sold — goods move
 * between two of the company's own locations — and the only thing that makes
 * the request real is that the stock ends up where it was asked for. This class
 * is the single place that movement is decided.
 *
 * ## Two ways goods travel, and why the difference matters
 *
 * **Delivery** — someone carries them. The goods leave the source when it
 * approves and ships, spend time on a road belonging to neither warehouse, and
 * arrive when the branch confirms. Two events, separated in time.
 *
 * **Pickup** — the rep collects them at the counter. Approval sets them aside
 * but moves nothing; the goods leave the source and enter the branch in the
 * same moment, when the rep signs for them. One event.
 *
 * Modelling pickup as a delivery would mean booking stock out of the main
 * warehouse while it is still on its shelf, so anything asking "what can we
 * sell today?" would be told less than the truth for as long as the rep took to
 * arrive. Hence the two paths.
 *
 * ## Why this is a service and not two controllers
 *
 * These rules previously lived twice: once in InventoryTransferController for
 * the back office, once in FieldReplenishmentController for the app. They had
 * already drifted — the two disagreed about what a receipt without an explicit
 * quantity meant — and every rule added to one was a rule missing from the
 * other. Both now come through here.
 */
class ReplenishmentWorkflowService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * The source warehouse agrees to the request and says how it will be met.
     *
     * @param  string  $method  InventoryTransfer::METHOD_DELIVERY|METHOD_PICKUP
     * @return array<string,mixed>  what the approval changed
     *
     * @throws RuntimeException
     */
    public function approve(InventoryTransfer $transfer, string $method, ?int $userId = null): array
    {
        if (!in_array($method, [InventoryTransfer::METHOD_DELIVERY, InventoryTransfer::METHOD_PICKUP], true)) {
            throw new RuntimeException('طريقة تنفيذ غير معروفة: ' . $method);
        }

        return DB::transaction(function () use ($transfer, $method, $userId) {
            // Locked first: two warehouse staff approving at the same moment
            // would otherwise both pass the check below and ship twice.
            $transfer = InventoryTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $transfer->load('items.product');

            if (!$transfer->canApprove()) {
                throw new RuntimeException(
                    'لا يمكن الموافقة على طلب في حالة "' . $transfer->status_text . '".'
                );
            }

            $transfer->fulfillment_method = $method;
            $transfer->approved_at = now();
            $transfer->approved_by = $userId;

            $effects = ['method' => $method];

            if ($method === InventoryTransfer::METHOD_DELIVERY) {
                // The goods leave now. The reservation taken when the request
                // was raised is consumed by the issue rather than released, so
                // the units can never be sold in the gap between the two.
                $effects['movements'] = $this->issueFromSource($transfer);
                $transfer->status = InventoryTransfer::STATUS_IN_TRANSIT;
                $transfer->shipped_at = now();
                $transfer->shipped_by = $userId;
            } else {
                // Set aside, not moved. The reservation is what holds them.
                $transfer->status = InventoryTransfer::STATUS_READY_FOR_PICKUP;
            }

            $transfer->save();

            return $effects + ['status' => $transfer->status];
        });
    }

    /**
     * The requester takes delivery, and the stock lands in their warehouse.
     *
     * This is the step the whole request exists for: whichever way the goods
     * travelled, they are in the branch's warehouse when it returns.
     *
     * Quantities are taken per line rather than assumed. A short delivery and a
     * rep who collects only part of what they asked for are both ordinary, and
     * booking the requested amount would invent stock that never arrived.
     *
     * @param  array<int,int>  $receivedByItem  transfer item id => quantity
     * @return array<string,mixed>
     *
     * @throws RuntimeException
     */
    public function receive(InventoryTransfer $transfer, array $receivedByItem = [], ?int $userId = null): array
    {
        return DB::transaction(function () use ($transfer, $receivedByItem, $userId) {
            $transfer = InventoryTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $transfer->load('items.product');

            if (!$transfer->canReceive()) {
                throw new RuntimeException(
                    'لا يمكن استلام طلب في حالة "' . $transfer->status_text . '".'
                );
            }

            $isPickup = $transfer->isPickup();
            $movements = [];
            $discrepancies = [];

            foreach ($transfer->items as $item) {
                // What the line is measured against differs by path: a delivery
                // can only bring what was put on the van, a pickup can only give
                // what was set aside.
                $ceiling = $isPickup
                    ? (int) $item->quantity_requested
                    : (int) ($item->quantity_shipped ?: $item->quantity_requested);

                $quantity = array_key_exists($item->id, $receivedByItem)
                    ? max(0, (int) $receivedByItem[$item->id])
                    : $ceiling;

                if ($quantity > $ceiling) {
                    throw new RuntimeException(sprintf(
                        'الكمية المستلمة من "%s" (%d) تتجاوز الكمية المتاحة في الطلب (%d).',
                        $this->productName($item),
                        $quantity,
                        $ceiling
                    ));
                }

                if ($isPickup) {
                    // Out and in together. The out-leg has to happen here
                    // because approval moved nothing.
                    if ($quantity > 0) {
                        $movements[] = $this->issueItem($transfer, $item, $quantity);
                    }

                    // Whatever the rep left behind goes back on sale. Without
                    // this the untaken remainder would stay reserved for a
                    // request that is now closed, invisible and unsellable.
                    $untaken = (int) $item->quantity_requested - $quantity;
                    if ($untaken > 0) {
                        $this->inventory->release(
                            (int) $item->product_id,
                            $untaken,
                            (int) $transfer->from_warehouse_id
                        );
                    }

                    $item->quantity_shipped = $quantity;
                }

                if ($quantity > 0) {
                    $movement = $this->inventory->receive(
                        (int) $item->product_id,
                        $quantity,
                        (int) $transfer->to_warehouse_id,
                        [
                            'key' => 'transfer:' . $transfer->id . ':item:' . $item->id . ':in',
                            'reference' => $transfer->transfer_number,
                            'source' => 'transfer',
                            'reason' => 'استلام تزويد من ' . ($transfer->fromWarehouse->name ?? 'المستودع المصدر')
                                . ' - ' . $transfer->transfer_number,
                            'unit_cost' => (float) $item->unit_cost,
                            'created_by' => $userId,
                        ]
                    );

                    if ($movement) {
                        $movements[] = $movement->id;
                    }
                }

                // A delivery that arrives short means goods left the source and
                // never got here. Reported rather than quietly absorbed: the
                // difference is a loss somebody has to account for.
                $shortfall = $ceiling - $quantity;
                if (!$isPickup && $shortfall > 0) {
                    $discrepancies[] = [
                        'product_id' => (int) $item->product_id,
                        'product_name' => $this->productName($item),
                        'shipped' => $ceiling,
                        'received' => $quantity,
                        'missing' => $shortfall,
                    ];
                }

                $item->quantity_received = $quantity;
                $item->save();
            }

            $transfer->status = InventoryTransfer::STATUS_COMPLETED;
            $transfer->received_at = now();
            $transfer->received_by = $userId;
            $transfer->save();

            return [
                'status' => $transfer->status,
                'movements' => $movements,
                'discrepancies' => $discrepancies,
            ];
        });
    }

    /**
     * Calls the request off and puts the held stock back on sale.
     *
     * Only while the goods are still at the source. Once they are in transit
     * they are on a road, and bringing them back is a return with its own
     * movements — not the absence of a transfer.
     *
     * @throws RuntimeException
     */
    public function cancel(InventoryTransfer $transfer, ?int $userId = null, ?string $reason = null): array
    {
        return DB::transaction(function () use ($transfer, $userId, $reason) {
            $transfer = InventoryTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $transfer->load('items');

            if (!$transfer->canCancel()) {
                throw new RuntimeException(
                    'لا يمكن إلغاء طلب في حالة "' . $transfer->status_text . '". '
                        . 'البضاعة غادرت المستودع وتحتاج إرجاعاً لا إلغاءً.'
                );
            }

            foreach ($transfer->items as $item) {
                $this->inventory->release(
                    (int) $item->product_id,
                    (int) $item->quantity_requested,
                    (int) $transfer->from_warehouse_id
                );
            }

            $transfer->status = InventoryTransfer::STATUS_CANCELLED;

            if ($reason) {
                $transfer->notes = trim(($transfer->notes ? $transfer->notes . ' — ' : '') . 'سبب الإلغاء: ' . $reason);
            }

            $transfer->save();

            return ['status' => $transfer->status, 'reservation_released' => true];
        });
    }

    /* ------------------------------------------------------------------ */

    /**
     * Takes every line out of the source warehouse.
     *
     * @return array<int,int>  movement ids
     */
    private function issueFromSource(InventoryTransfer $transfer): array
    {
        $movements = [];

        foreach ($transfer->items as $item) {
            $quantity = (int) $item->quantity_requested;

            if ($quantity <= 0) {
                continue;
            }

            $movements[] = $this->issueItem($transfer, $item, $quantity);

            $item->quantity_shipped = $quantity;
            $item->save();
        }

        return array_values(array_filter($movements));
    }

    /**
     * One line leaving the source, consuming the hold placed on it.
     *
     * @throws RuntimeException when the shelf can no longer cover it
     */
    private function issueItem(InventoryTransfer $transfer, InventoryTransferItem $item, int $quantity): ?int
    {
        try {
            $movement = $this->inventory->shipReserved(
                (int) $item->product_id,
                $quantity,
                (int) $transfer->from_warehouse_id,
                [
                    // Keyed per item, so replaying the step — a double tap, a
                    // retried request — cannot move the goods twice.
                    'key' => 'transfer:' . $transfer->id . ':item:' . $item->id . ':out',
                    'reference' => $transfer->transfer_number,
                    'source' => 'transfer',
                    'reason' => 'إخراج مخزون لطلب تزويد ' . $transfer->transfer_number,
                    'unit_cost' => (float) $item->unit_cost,
                ]
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(sprintf(
                'تعذّر إخراج %d من "%s" من المستودع المصدر: %s',
                $quantity,
                $this->productName($item),
                $e->getMessage()
            ));
        }

        return $movement?->id;
    }

    private function productName(InventoryTransferItem $item): string
    {
        return $item->product?->name_ar
            ?? $item->product?->name_en
            ?? ('#' . $item->product_id);
    }
}
