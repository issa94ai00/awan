<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * What stock is actually worth, layer by layer.
 *
 * Cost used to be a single number on the product, so goods bought at 20 and
 * goods bought at 30 both left the warehouse at whatever the product record
 * said today. Every receipt now opens a layer at the price actually paid, and
 * every issue consumes the oldest layers first and reports the real cost of
 * the units it took.
 *
 * Layers are per warehouse: the same product held in two places was bought at
 * two prices, and a sale must be costed against the stock it came out of.
 */
class InventoryCostingService
{
    private const TABLE = 'inventory_cost_layers';

    /**
     * Opens a layer for stock arriving in a warehouse.
     *
     * A receipt with no cost still gets a layer. Skipping it would leave units
     * on the shelf that no issue could ever cost, and the shortfall would
     * silently fall back to the product price.
     */
    public function addLayer(
        int $productId,
        int $warehouseId,
        int $quantity,
        float $unitCost,
        array $options = []
    ): void {
        if ($quantity <= 0) {
            return;
        }

        DB::table(self::TABLE)->insert([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'received_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => round($unitCost, 4),
            'source' => $options['source'] ?? null,
            'reference' => $options['reference'] ?? null,
            'stock_movement_id' => $options['stock_movement_id'] ?? null,
            'received_at' => $options['received_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Consumes `quantity` units oldest-first and returns what they cost.
     *
     * Must run inside the caller's transaction: the layer rows are locked while
     * being drawn down, so two concurrent shipments cannot both spend the same
     * units at the same price.
     *
     * @return array{cost:float,unit_cost:float,layers:array<int,array{layer_id:int,quantity:int,unit_cost:float}>}
     */
    public function consume(int $productId, int $warehouseId, int $quantity): array
    {
        if ($quantity <= 0) {
            return ['cost' => 0.0, 'unit_cost' => 0.0, 'layers' => []];
        }

        $layers = DB::table(self::TABLE)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;
        $cost = 0.0;
        $consumed = [];

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $layer->remaining_quantity);

            DB::table(self::TABLE)
                ->where('id', $layer->id)
                ->update([
                    'remaining_quantity' => (int) $layer->remaining_quantity - $take,
                    'updated_at' => now(),
                ]);

            $cost += $take * (float) $layer->unit_cost;
            $consumed[] = [
                'layer_id' => (int) $layer->id,
                'quantity' => $take,
                'unit_cost' => (float) $layer->unit_cost,
            ];

            $remaining -= $take;
        }

        // More was issued than the layers account for — stock that arrived
        // before layering existed, or an adjustment that added units without a
        // cost. The product's cost price is the only figure left to value it
        // at, and using it keeps the shipment costed rather than free.
        if ($remaining > 0) {
            $fallback = (float) (Product::whereKey($productId)->value('cost_price') ?? 0);
            $cost += $remaining * $fallback;
            $consumed[] = [
                'layer_id' => 0,
                'quantity' => $remaining,
                'unit_cost' => $fallback,
            ];
        }

        $cost = round($cost, 2);

        return [
            'cost' => $cost,
            'unit_cost' => $quantity > 0 ? round($cost / $quantity, 4) : 0.0,
            'layers' => $consumed,
        ];
    }

    /**
     * Returns units to stock by reopening a layer at the cost they left at.
     *
     * A return must come back in at what it went out at, not at today's price —
     * otherwise cancelling a sale quietly changes what the inventory is worth.
     */
    public function returnLayer(int $productId, int $warehouseId, int $quantity, float $unitCost): void
    {
        $this->addLayer($productId, $warehouseId, $quantity, $unitCost, [
            'source' => 'return',
            'reference' => 'إرجاع بضاعة',
        ]);
    }

    /** The value of everything currently held, at what was paid for it. */
    public function valueOnHand(?int $warehouseId = null): float
    {
        return (float) DB::table(self::TABLE)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('COALESCE(SUM(remaining_quantity * unit_cost), 0) as value')
            ->value('value');
    }
}
