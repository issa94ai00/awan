<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single path through which stock is allowed to move.
 *
 * Inventory was previously tracked in two disconnected places. `products.
 * stock_quantity` was maintained by a model hook on StockMovement, while the
 * whole WMS layer (allocation, picking, transfers, reorder points) reads
 * `warehouse_inventory` — a table nothing kept in step with it. Selling made no
 * difference to either: invoices, sales orders and the POS never touched stock
 * at all, so quantities only ever went up.
 *
 * Everything here keeps the three records consistent in one transaction:
 *
 *   stock_movements     the audit trail — what moved, when, why
 *   warehouse_inventory the per-warehouse position the WMS works from
 *   products.stock_quantity  the cached company-wide total
 *
 * Movements are idempotent: each carries a `movement_key` derived from the
 * document that caused it, so a repeated event cannot move stock twice.
 */
class InventoryService
{
    /**
     * Condition buckets inside a warehouse row. `available` is the only one
     * that can be sold; damaged and quarantined sit in `quantity` but are
     * excluded from availability.
     */
    public const CONDITION_AVAILABLE = 'available';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_QUARANTINED = 'quarantined';

    /**
     * Issues stock out — a sale, a shipment, a write-off.
     *
     * @throws RuntimeException when there is not enough available stock
     */
    public function issue(
        int $productId,
        int $quantity,
        ?int $warehouseId = null,
        array $options = [],
        ?int $productVariantId = null
    ): ?StockMovement {
        if ($quantity <= 0) {
            return null;
        }

        return $this->move($productId, -$quantity, $warehouseId, StockMovement::TYPE_OUT, $options, $productVariantId);
    }

    /**
     * Consume stock that was reserved for an order or transfer.
     *
     * This is a clear semantic helper for paths that reserve first and then ship.
     */
    public function shipReserved(
        int $productId,
        int $quantity,
        ?int $warehouseId = null,
        array $options = [],
        ?int $productVariantId = null
    ): ?StockMovement {
        return $this->issue($productId, $quantity, $warehouseId, $options + ['consume_reserved' => true], $productVariantId);
    }

    /** Receives stock in — a purchase receipt, a customer return. */
    public function receive(
        int $productId,
        int $quantity,
        ?int $warehouseId = null,
        array $options = []
    ): ?StockMovement {
        if ($quantity <= 0) {
            return null;
        }

        return $this->move($productId, $quantity, $warehouseId, StockMovement::TYPE_IN, $options);
    }

    /**
     * Signed correction from a stock count. Positive found, negative shrinkage.
     * Allowed to drive stock negative only if the caller explicitly permits it,
     * because a count is a statement of fact.
     *
     * The difference reaches the ledger in the same transaction as the stock.
     * It used to move the warehouse alone, so the inventory asset kept
     * describing goods that had been counted away — and the loss never
     * appeared as a cost anywhere. Both records now change together or
     * neither does: a posting that cannot be written rolls the movement back
     * rather than leaving the two disagreeing.
     *
     * @param  array{post_to_ledger?:bool}  $options  `post_to_ledger` => false
     *         for a caller that posts the accounting side itself
     */
    public function adjust(
        int $productId,
        int $signedQuantity,
        ?int $warehouseId = null,
        array $options = []
    ): ?StockMovement {
        if ($signedQuantity === 0) {
            return null;
        }

        return DB::transaction(function () use ($productId, $signedQuantity, $warehouseId, $options) {
            $movement = $this->move(
                $productId,
                $signedQuantity,
                $warehouseId,
                StockMovement::TYPE_ADJUSTMENT,
                $options + ['allow_negative' => true]
            );

            if ($movement && ($options['post_to_ledger'] ?? true)) {
                $this->postAdjustmentToLedger($movement, $options);
            }

            return $movement;
        });
    }

    /**
     * Books the value of an adjustment against the shrinkage account.
     *
     * The cost comes off the movement rather than the caller: for stock going
     * out that is what the FIFO layers actually gave up, which is the only
     * figure that leaves the inventory account holding what is really on the
     * shelf.
     *
     * Keyed on the movement, so a repeated adjustment — which `move` already
     * returns unchanged — cannot post its value twice.
     */
    private function postAdjustmentToLedger(StockMovement $movement, array $options): void
    {
        $cost = abs((float) $movement->total_cost);

        if ($cost <= 0) {
            return;
        }

        $label = $options['reason']
            ?? $options['reference']
            ?? ('حركة #'.$movement->id);

        app(LedgerPostingService::class)->postInventoryAdjustment(
            key: 'stock_adjustment:'.$movement->id,
            warehouseId: (int) $movement->warehouse_id,
            cost: $cost,
            isShortage: (int) $movement->quantity < 0,
            label: (string) $label,
            reference: $movement,
            date: $movement->created_at?->toDateString(),
        );
    }

    /**
     * Core movement.
     *
     * @param  int  $signedQuantity  negative takes stock out, positive puts it in
     * @param  array{key?:string,reason?:string,reference?:string,source?:string,bin_id?:int,unit_cost?:float,condition?:string,allow_negative?:bool,consume_reserved?:bool,created_by?:int}  $options
     */
    public function move(
        int $productId,
        int $signedQuantity,
        ?int $warehouseId,
        string $movementType,
        array $options = [],
        ?int $productVariantId = null
    ): ?StockMovement {
        if ($signedQuantity === 0) {
            return null;
        }

        $key = $options['key'] ?? null;

        if ($key) {
            $existing = StockMovement::where('movement_key', $key)->first();
            if ($existing) {
                return $existing;
            }
        }

        $warehouseId = $warehouseId ?: $this->defaultWarehouseId();
        if (! $warehouseId) {
            throw new RuntimeException('لا يوجد مستودع لتسجيل حركة المخزون.');
        }

        $condition = $options['condition'] ?? self::CONDITION_AVAILABLE;
        $allowNegative = (bool) ($options['allow_negative'] ?? false);

        return DB::transaction(function () use (
            $productId, $signedQuantity, $warehouseId, $movementType, $options, $key, $condition, $allowNegative, $productVariantId
        ) {
            // Re-check inside the transaction: two concurrent requests for the
            // same document would otherwise both get past the check above.
            if ($key) {
                $existing = StockMovement::where('movement_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
            }

            $consumeReserved = (bool) ($options['consume_reserved'] ?? false);

            // Shipping reserved stock must lock the same shelf the hold was taken
            // on. An arbitrary first() across bin rows left the reserved balance
            // untouched while another row's quantity moved — the order looked
            // shipped and the inventory screen looked unchanged.
            $row = $this->lockedInventoryRow(
                $productId,
                $warehouseId,
                $options['bin_id'] ?? null,
                $productVariantId,
                preferReserved: $consumeReserved && $signedQuantity < 0
            );

            $bucket = $this->bucketColumn($condition);

            if ($signedQuantity < 0 && ! $allowNegative) {
                $availableInBucket = (int) $row->{$bucket};

                if (! $consumeReserved && $condition === self::CONDITION_AVAILABLE) {
                    $availableInBucket -= (int) $row->reserved_quantity;
                }

                if ($availableInBucket < abs($signedQuantity)) {
                    throw new RuntimeException(sprintf(
                        'الكمية المتاحة غير كافية للمنتج #%d في المستودع #%d: المتاح %d، المطلوب %d.',
                        $productId,
                        $warehouseId,
                        max(0, $availableInBucket),
                        abs($signedQuantity)
                    ));
                }

                if ($consumeReserved && $condition === self::CONDITION_AVAILABLE) {
                    // Refuse to ship against a row that never held the reservation.
                    // Clamping to zero used to hide that and leave the real hold
                    // stuck on another warehouse_inventory row.
                    if ((int) $row->reserved_quantity < abs($signedQuantity)) {
                        throw new RuntimeException(sprintf(
                            'لا يوجد حجز كافٍ للمنتج #%d في المستودع #%d: المحجوز %d، المطلوب %d.',
                            $productId,
                            $warehouseId,
                            (int) $row->reserved_quantity,
                            abs($signedQuantity)
                        ));
                    }

                    $row->reserved_quantity = (int) $row->reserved_quantity - abs($signedQuantity);
                }
            }

            $row->quantity = (int) $row->quantity + $signedQuantity;
            $row->{$bucket} = (int) $row->{$bucket} + $signedQuantity;
            $row->save();

            // FIFO layers ride on the same locked transaction as the balance,
            // so the two can never disagree about what is on the shelf or what
            // it cost. Stock leaving is costed against the oldest layers; the
            // real figure replaces whatever the caller guessed at.
            $costing = app(InventoryCostingService::class);
            $unitCost = (float) ($options['unit_cost'] ?? 0);
            $costedTotal = null;

            if ($signedQuantity < 0) {
                $consumed = $costing->consume($productId, $warehouseId, abs($signedQuantity));
                $unitCost = $consumed['unit_cost'];
                $costedTotal = $consumed['cost'];
            } else {
                $costing->addLayer($productId, $warehouseId, $signedQuantity, $unitCost, [
                    'source' => $options['source'] ?? null,
                    'reference' => $options['reference'] ?? null,
                ]);

                // A purchase moves the reference price, not just the FIFO layers:
                // callers that pass this ask for products.cost_price to become a
                // quantity-weighted average of what was on hand and what was just
                // paid, so the figure shown around the app keeps tracking reality
                // instead of freezing at whatever the product was created with.
                if ($options['update_average_cost'] ?? false) {
                    $this->applyWeightedAverageCost($productId, $signedQuantity, $unitCost);
                }

                // The shelf price is a decision the operator made when they
                // typed it on the receipt, not a figure to blend with the old
                // one — so, unlike cost, it is set outright rather than averaged.
                if (! empty($options['sale_price'])) {
                    $this->applySalePrice($productId, (float) $options['sale_price']);
                }
            }

            // The model hook on StockMovement maintains products.stock_quantity,
            // so it is deliberately not touched here — doing both would double
            // every movement.
            $movement = StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'movement_type' => $movementType,
                // The column is a magnitude for in/out; direction is carried by
                // the type. Adjustments keep their sign so a shrinkage reads as
                // negative in the ledger of movements.
                'quantity' => $movementType === StockMovement::TYPE_ADJUSTMENT
                    ? $signedQuantity
                    : abs($signedQuantity),
                'movement_key' => $key,
                'reference' => $options['reference'] ?? null,
                'source' => $options['source'] ?? null,
                'notes' => $options['reason'] ?? null,
                // The movement records what the units were actually worth: for
                // an issue that is the FIFO cost just consumed, not the caller's
                // estimate.
                'unit_cost' => round($unitCost, 5),
                'total_cost' => $costedTotal ?? round($unitCost * abs($signedQuantity), 5),
                'created_by' => $options['created_by'] ?? auth()->id(),
            ]);

            return $movement;
        });
    }

    /**
     * Moves stock between two warehouses in a single transaction.
     *
     * One movement pair is written (an `out` from the source, an `in` into the
     * destination) and both are idempotent under the same `key`. The destination
     * receive is keyed `{$key}:in` and the source issue `{$key}:out`, so a
     * repeated transfer can never ship stock it already shipped.
     *
     * This is the whole-document primitive for a completed transfer. The
     * transfer controller keeps its two-step ship/receive lifecycle for partial
     * receipts; callers that need an atomic move (corrections, initial stock
     * placement) should use this.
     *
     * @return array{0:?StockMovement,1:?StockMovement} [out, in]
     *
     * @throws RuntimeException when the source warehouse lacks available stock
     */
    public function transfer(
        int $productId,
        int $quantity,
        int $fromWarehouseId,
        int $toWarehouseId,
        array $options = []
    ): array {
        if ($quantity <= 0) {
            return [null, null];
        }

        $baseKey = $options['key'] ?? null;

        return DB::transaction(function () use ($productId, $quantity, $fromWarehouseId, $toWarehouseId, $options, $baseKey) {
            $out = $this->issue($productId, $quantity, $fromWarehouseId, $options + [
                'key' => $baseKey ? $baseKey.':out' : null,
                'reference' => $options['reference'] ?? null,
                'source' => $options['source'] ?? 'transfer',
            ]);

            $in = $this->receive($productId, $quantity, $toWarehouseId, $options + [
                'key' => $baseKey ? $baseKey.':in' : null,
                'reference' => $options['reference'] ?? null,
                'source' => $options['source'] ?? 'transfer',
            ]);

            return [$out, $in];
        });
    }

    /* ------------------------------------------------------------------ *
     * Reservation
     * ------------------------------------------------------------------ */

    /**
     * Holds stock for a confirmed order without shipping it yet. Reserved units
     * stay in `quantity` but stop being sellable, which is what keeps two orders
     * from promising the same unit.
     */
    public function reserve(int $productId, int $quantity, ?int $warehouseId = null, ?int $productVariantId = null): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        $warehouseId = $warehouseId ?: $this->defaultWarehouseId();
        if (! $warehouseId) {
            return false;
        }

        return DB::transaction(function () use ($productId, $quantity, $warehouseId, $productVariantId) {
            $row = $this->lockedInventoryRow($productId, $warehouseId, null, $productVariantId);

            if ($this->sellableOn($row) < $quantity) {
                return false;
            }

            $row->reserved_quantity = (int) $row->reserved_quantity + $quantity;
            $row->save();

            return true;
        });
    }

    public function release(int $productId, int $quantity, ?int $warehouseId = null, ?int $productVariantId = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        $warehouseId = $warehouseId ?: $this->defaultWarehouseId();
        if (! $warehouseId) {
            return;
        }

        DB::transaction(function () use ($productId, $quantity, $warehouseId, $productVariantId) {
            $row = $this->lockedInventoryRow($productId, $warehouseId, null, $productVariantId);
            // Never let a release push the reservation below zero.
            $row->reserved_quantity = max(0, (int) $row->reserved_quantity - $quantity);
            $row->save();
        });
    }

    /* ------------------------------------------------------------------ *
     * Queries
     * ------------------------------------------------------------------ */

    /** Units that can actually be sold right now, across all warehouses. */
    public function sellableQuantity(int $productId, ?int $warehouseId = null): int
    {
        return (int) WarehouseInventory::query()
            ->where('product_id', $productId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->get()
            ->sum(fn ($row) => $this->sellableOn($row));
    }

    public function defaultWarehouseId(): ?int
    {
        return Warehouse::query()->orderBy('id')->value('id');
    }

    /* ------------------------------------------------------------------ */

    private function sellableOn(WarehouseInventory $row): int
    {
        return max(0, (int) $row->available_quantity - (int) $row->reserved_quantity);
    }

    /**
     * Fetches (or opens) the warehouse row for a product and locks it for the
     * rest of the transaction, so concurrent movements cannot interleave.
     *
     * When no bin is named, several rows can exist for the same product in one
     * warehouse (a warehouse-level balance plus per-bin balances). Picking an
     * arbitrary first() made reserve and shipReserved disagree about which
     * shelf they meant. The rules below keep them on the same row:
     *
     *   - a named bin is always that bin
     *   - shipping reserved stock prefers the row that actually holds the hold
     *   - otherwise the warehouse-level (bin-less) row, or the fullest shelf
     */
    private function lockedInventoryRow(
        int $productId,
        int $warehouseId,
        ?int $binId = null,
        ?int $productVariantId = null,
        bool $preferReserved = false
    ): WarehouseInventory {
        $base = WarehouseInventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when(
                $productVariantId !== null,
                fn ($q) => $q->where('product_variant_id', $productVariantId)
            );

        if ($binId !== null) {
            $row = (clone $base)->where('bin_id', $binId)->lockForUpdate()->first();
            if ($row) {
                return $row;
            }
        } else {
            if ($preferReserved) {
                $row = (clone $base)
                    ->where('reserved_quantity', '>', 0)
                    ->orderByDesc('reserved_quantity')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($row) {
                    return $row;
                }
            }

            $warehouseLevel = (clone $base)
                ->whereNull('bin_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            // Prefer the warehouse-level balance when it can actually be sold from,
            // so reserve and ship stay on one row instead of drifting to a bin.
            if ($warehouseLevel && $this->sellableOn($warehouseLevel) > 0) {
                return $warehouseLevel;
            }

            $row = (clone $base)
                ->orderByDesc(DB::raw('available_quantity - reserved_quantity'))
                ->orderByDesc('available_quantity')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($row) {
                return $row;
            }

            if ($warehouseLevel) {
                return $warehouseLevel;
            }
        }

        return WarehouseInventory::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'bin_id' => $binId,
            'quantity' => 0,
            'available_quantity' => 0,
            'damaged_quantity' => 0,
            'quarantined_quantity' => 0,
            'reserved_quantity' => 0,
            'cost_basis' => WarehouseInventory::COST_BASIS_FIFO,
        ]);
    }

    /**
     * Rolls a purchase price into products.cost_price as a quantity-weighted
     * average of the stock already on hand and what just arrived.
     *
     * Locked on the product row so two concurrent receipts for the same
     * product cannot both average against the same starting figure. Reads
     * stock_quantity as it stands right now: the StockMovement hook that
     * bumps it for this receipt has not run yet, so it is still the
     * pre-receipt total.
     */
    private function applyWeightedAverageCost(int $productId, int $receivedQuantity, float $unitCost): void
    {
        $product = Product::whereKey($productId)->lockForUpdate()->first();
        if (! $product) {
            return;
        }

        $oldQuantity = max(0, (int) $product->stock_quantity);
        $oldCost = (float) $product->cost_price;

        $newCost = $oldQuantity > 0
            ? (($oldQuantity * $oldCost) + ($receivedQuantity * $unitCost)) / ($oldQuantity + $receivedQuantity)
            : $unitCost;

        $product->cost_price = round($newCost, 5);
        $product->save();
    }

    /**
     * Sets the product's retail price to what the receipt line asked for.
     *
     * No lock and no read of the current value: this is a plain overwrite,
     * not a computation built from it, so there is nothing to race.
     */
    private function applySalePrice(int $productId, float $salePrice): void
    {
        if ($salePrice <= 0) {
            return;
        }

        Product::whereKey($productId)->update(['price' => round($salePrice, 5)]);
    }

    private function bucketColumn(string $condition): string
    {
        return match ($condition) {
            self::CONDITION_DAMAGED => 'damaged_quantity',
            self::CONDITION_QUARANTINED => 'quarantined_quantity',
            default => 'available_quantity',
        };
    }
}
