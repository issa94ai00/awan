<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
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
        array $options = []
    ): ?StockMovement {
        if ($quantity <= 0) {
            return null;
        }

        return $this->move($productId, -$quantity, $warehouseId, StockMovement::TYPE_OUT, $options);
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

        return $this->move(
            $productId,
            $signedQuantity,
            $warehouseId,
            StockMovement::TYPE_ADJUSTMENT,
            $options + ['allow_negative' => true]
        );
    }

    /**
     * Core movement.
     *
     * @param  int  $signedQuantity  negative takes stock out, positive puts it in
     * @param  array{key?:string,reason?:string,reference?:string,source?:string,bin_id?:int,unit_cost?:float,condition?:string,allow_negative?:bool,created_by?:int}  $options
     */
    public function move(
        int $productId,
        int $signedQuantity,
        ?int $warehouseId,
        string $movementType,
        array $options = []
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
        if (!$warehouseId) {
            throw new RuntimeException('لا يوجد مستودع لتسجيل حركة المخزون.');
        }

        $condition = $options['condition'] ?? self::CONDITION_AVAILABLE;
        $allowNegative = (bool) ($options['allow_negative'] ?? false);

        return DB::transaction(function () use (
            $productId, $signedQuantity, $warehouseId, $movementType, $options, $key, $condition, $allowNegative
        ) {
            // Re-check inside the transaction: two concurrent requests for the
            // same document would otherwise both get past the check above.
            if ($key) {
                $existing = StockMovement::where('movement_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
            }

            $row = $this->lockedInventoryRow($productId, $warehouseId, $options['bin_id'] ?? null);

            $bucket = $this->bucketColumn($condition);

            if ($signedQuantity < 0 && !$allowNegative) {
                $availableInBucket = (int) $row->{$bucket} - (int) $row->reserved_quantity;
                if ($availableInBucket < abs($signedQuantity)) {
                    throw new RuntimeException(sprintf(
                        'الكمية المتاحة غير كافية للمنتج #%d في المستودع #%d: المتاح %d، المطلوب %d.',
                        $productId,
                        $warehouseId,
                        max(0, $availableInBucket),
                        abs($signedQuantity)
                    ));
                }
            }

            $row->quantity = (int) $row->quantity + $signedQuantity;
            $row->{$bucket} = (int) $row->{$bucket} + $signedQuantity;
            $row->save();

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
                'unit_cost' => $options['unit_cost'] ?? 0,
                'total_cost' => round((float) ($options['unit_cost'] ?? 0) * abs($signedQuantity), 2),
                'created_by' => $options['created_by'] ?? auth()->id(),
            ]);

            return $movement;
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
    public function reserve(int $productId, int $quantity, ?int $warehouseId = null): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        $warehouseId = $warehouseId ?: $this->defaultWarehouseId();
        if (!$warehouseId) {
            return false;
        }

        return DB::transaction(function () use ($productId, $quantity, $warehouseId) {
            $row = $this->lockedInventoryRow($productId, $warehouseId);

            if ($this->sellableOn($row) < $quantity) {
                return false;
            }

            $row->reserved_quantity = (int) $row->reserved_quantity + $quantity;
            $row->save();

            return true;
        });
    }

    public function release(int $productId, int $quantity, ?int $warehouseId = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        $warehouseId = $warehouseId ?: $this->defaultWarehouseId();
        if (!$warehouseId) {
            return;
        }

        DB::transaction(function () use ($productId, $quantity, $warehouseId) {
            $row = $this->lockedInventoryRow($productId, $warehouseId);
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
     */
    private function lockedInventoryRow(int $productId, int $warehouseId, ?int $binId = null): WarehouseInventory
    {
        $row = WarehouseInventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when($binId, fn ($q) => $q->where('bin_id', $binId))
            ->lockForUpdate()
            ->first();

        if ($row) {
            return $row;
        }

        return WarehouseInventory::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'bin_id' => $binId,
            'quantity' => 0,
            'available_quantity' => 0,
            'damaged_quantity' => 0,
            'quarantined_quantity' => 0,
            'reserved_quantity' => 0,
            'cost_basis' => WarehouseInventory::COST_BASIS_FIFO,
        ]);
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
