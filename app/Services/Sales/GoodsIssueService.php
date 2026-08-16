<?php

namespace App\Services\Sales;

use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Inventory\InventoryService;
use RuntimeException;

/**
 * Taking goods off the shelf and telling the ledger what they cost.
 *
 * Two things have to happen together when stock leaves on a sale, and they are
 * easy to let drift apart: the warehouse row goes down, and cost of sales goes
 * up by what those particular units actually cost. Recording one without the
 * other is the classic way a system reports a healthy margin on goods it can no
 * longer find.
 *
 * The sales-order path already did this correctly on shipment. The direct
 * invoice path did not — it issued stock with no warehouse named and posted no
 * cost entry at all, so every invoice raised from the dashboard overstated gross
 * profit by the whole cost of the goods. Rather than copy the working version
 * into the second caller and let the two versions diverge, the shared part lives
 * here and both paths call it.
 *
 * What stays with each caller is what genuinely differs: the sales order decides
 * *where* each unit comes from through its allocations, while a direct sale is
 * told the warehouse per line by whoever is selling.
 */
class GoodsIssueService
{
    public function __construct(
        private InventoryService $inventory,
        private LedgerPostingService $ledger,
    ) {}

    /**
     * Checks that every line can actually be filled before anything moves.
     *
     * Called before the issue rather than relying on it to fail, because the
     * alternative that was in place — `allow_negative` — let a sale proceed and
     * drove the warehouse below zero. A negative shelf is not a smaller number;
     * it is a record that no longer describes anything real, and it silently
     * corrupts every valuation and reorder decision downstream.
     *
     * Quantities are pooled per (product, warehouse) so two lines of the same
     * item from the same place cannot both be told the last unit is theirs.
     *
     * @param  list<array{product_id:int, quantity:int, warehouse_id:int}>  $lines
     * @return list<array{product_id:int, warehouse_id:int, required:int, available:int, shortfall:int}>
     *         the lines that cannot be filled; empty when the sale can go ahead
     */
    public function shortagesFor(array $lines): array
    {
        $required = [];

        foreach ($lines as $line) {
            $key = $line['product_id'].':'.$line['warehouse_id'];
            $required[$key] = ($required[$key] ?? 0) + (int) $line['quantity'];
        }

        $shortages = [];

        foreach ($required as $key => $quantity) {
            [$productId, $warehouseId] = array_map('intval', explode(':', $key));

            $available = $this->inventory->sellableQuantity($productId, $warehouseId);

            if ($available >= $quantity) {
                continue;
            }

            $shortages[] = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'required' => $quantity,
                'available' => max(0, $available),
                'shortfall' => $quantity - max(0, $available),
            ];
        }

        return $shortages;
    }

    /**
     * Issues the lines and posts the matching cost of sales.
     *
     * @param  list<array{product_id:int, quantity:int, warehouse_id:int, unit_cost?:float, movement_key:string}>  $lines
     * @param  string  $postingKey  e.g. "invoice_cogs:12" — the handle the entry
     *                              is reversed by, so cancelling can undo it.
     * @param  bool  $consumeReserved  true where the stock was already held for
     *   this sale. A confirmed sales order reserves at confirmation, so shipping
     *   must spend that reservation rather than demand fresh availability —
     *   otherwise the order's own hold would be what blocks it from shipping. A
     *   direct sale reserves nothing, so it takes from what is free.
     * @return array{movements: list<int>, cost: float, cost_by_warehouse: array<int,float>}
     */
    public function issueAndPostCost(
        array $lines,
        string $postingKey,
        string $label,
        $reference = null,
        ?string $currency = null,
        string $reason = '',
        bool $consumeReserved = false,
        // Stamped onto each stock movement so the warehouse ledger says which
        // document moved the goods. Left to the caller because the two paths
        // label themselves differently and reports group on these.
        string $movementReference = 'sales',
        string|int|null $movementSource = null,
    ): array {
        $movements = [];
        $costByWarehouse = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $quantity = (int) $line['quantity'];
            if ($quantity <= 0) {
                continue;
            }

            $warehouseId = (int) $line['warehouse_id'];
            $unitCost = (float) ($line['unit_cost'] ?? 0);

            $options = [
                // Keyed per product *and* warehouse: one line split across
                // two places writes two movements, and a shared key would
                // make the second look like a repeat and be skipped.
                'key' => $line['movement_key'],
                'source' => $movementSource,
                'reference' => $movementReference,
                'reason' => $reason ?: $label,
                'unit_cost' => $unitCost,
                // Coverage is checked before this runs. Letting the shelf go
                // negative here would hide exactly the problem that check
                // exists to surface.
                'allow_negative' => false,
            ];

            $movement = $consumeReserved
                ? $this->inventory->shipReserved((int) $line['product_id'], $quantity, $warehouseId, $options)
                : $this->inventory->issue((int) $line['product_id'], $quantity, $warehouseId, $options);

            if ($movement) {
                $movements[] = $movement->id;
            }

            // The movement carries what the units actually cost, drawn from the
            // FIFO layers as they were consumed. Falling back to the product's
            // current cost values a batch bought at 20 and one bought at 30
            // identically, and the margin is wrong by the difference per unit.
            $lineCost = $movement ? (float) $movement->total_cost : $unitCost * $quantity;

            $total += $lineCost;
            $costByWarehouse[$warehouseId] = ($costByWarehouse[$warehouseId] ?? 0) + $lineCost;
        }

        $this->ledger->postCostOfGoodsSoldBySource(
            key: $postingKey,
            costByWarehouse: $costByWarehouse,
            label: $label,
            reference: $reference,
            currency: $currency,
        );

        return [
            'movements' => $movements,
            'cost' => round($total, 2),
            'cost_by_warehouse' => $costByWarehouse,
        ];
    }

    /**
     * Puts the goods back and undoes the cost entry.
     *
     * The mirror of `issueAndPostCost`, for a sale that is cancelled. Both
     * halves matter: returning the stock without reversing the entry leaves the
     * books carrying a cost for goods that are back on the shelf, and reversing
     * without returning leaves the shelf short.
     *
     * @param  list<array{product_id:int, quantity:int, warehouse_id:int, unit_cost?:float, movement_key:string}>  $lines
     */
    public function returnAndReverseCost(
        array $lines,
        string $postingKey,
        string $label,
        string $reason = '',
    ): void {
        foreach ($lines as $line) {
            $quantity = (int) $line['quantity'];
            if ($quantity <= 0) {
                continue;
            }

            $this->inventory->receive(
                (int) $line['product_id'],
                $quantity,
                (int) $line['warehouse_id'],
                [
                    // A distinct key from the issue, or the movement would be
                    // read as a repeat of it and skipped.
                    'key' => $line['movement_key'],
                    'source' => 'sales',
                    'reference' => $label,
                    'reason' => $reason ?: $label,
                    'unit_cost' => (float) ($line['unit_cost'] ?? 0),
                ]
            );
        }

        $this->ledger->reverseFor($postingKey);
    }

    /**
     * The warehouse to use when a caller names none.
     *
     * Deliberately not a silent fallback to "the first warehouse by id", which
     * is what the invoice path did: it issued every sale from whichever
     * warehouse happened to have the lowest id, regardless of where the goods
     * were, so one warehouse drifted negative while the real one stayed full.
     *
     * @throws RuntimeException when there is nothing sensible to choose
     */
    public function requireWarehouse(?int $warehouseId): int
    {
        if ($warehouseId) {
            return $warehouseId;
        }

        $only = Warehouse::query()->where('is_active', true)->limit(2)->pluck('id');

        // With exactly one active warehouse there is no ambiguity to resolve,
        // so asking would be pedantry. With several, guessing is the bug.
        if ($only->count() === 1) {
            return (int) $only->first();
        }

        throw new RuntimeException('يجب تحديد المستودع لكل صنف — يوجد أكثر من مستودع نشط.');
    }
}
