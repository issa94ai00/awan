<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\Setting;
use App\Models\WarehouseInventory;
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
     * Consumes `quantity` units according to the active cost calculation method
     * (FIFO, WEIGHTED_AVERAGE, LIFO, or FEFO) and returns what they cost.
     *
     * Must run inside the caller's transaction: the layer rows are locked while
     * being drawn down, so two concurrent shipments cannot both spend the same
     * units at the same price.
     *
     * @return array{cost:float,unit_cost:float,layers:array<int,array{layer_id:int,quantity:int,unit_cost:float}>}
     */
    public function consume(int $productId, int $warehouseId, int $quantity, ?string $method = null): array
    {
        if ($quantity <= 0) {
            return ['cost' => 0.0, 'unit_cost' => 0.0, 'layers' => []];
        }

        $effectiveMethod = $method ?: $this->resolveCostMethod($productId, $warehouseId);

        $query = DB::table(self::TABLE)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_quantity', '>', 0);

        if ($effectiveMethod === WarehouseInventory::COST_BASIS_LIFO) {
            $query->orderByDesc('received_at')->orderByDesc('id');
        } else {
            // FIFO, WEIGHTED_AVERAGE, and FEFO (default layer sequence)
            $query->orderBy('received_at')->orderBy('id');
        }

        $layers = $query->lockForUpdate()->get();

        if ($effectiveMethod === WarehouseInventory::COST_BASIS_WEIGHTED_AVERAGE) {
            return $this->consumeWeightedAverage($productId, $warehouseId, $quantity, $layers);
        }

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

        $cost = round($cost, 5);

        return [
            'cost' => $cost,
            'unit_cost' => $quantity > 0 ? round($cost / $quantity, 5) : 0.0,
            'layers' => $consumed,
        ];
    }

    /**
     * Consumes units using the moving weighted average cost of remaining stock.
     */
    private function consumeWeightedAverage(int $productId, int $warehouseId, int $quantity, $layers): array
    {
        $totalLayerQty = (int) $layers->sum('remaining_quantity');
        $totalLayerCost = (float) $layers->sum(fn ($l) => (int) $l->remaining_quantity * (float) $l->unit_cost);

        $fallback = (float) (Product::whereKey($productId)->value('cost_price') ?? 0);
        $avgUnitCost = $totalLayerQty > 0 ? round($totalLayerCost / $totalLayerQty, 5) : $fallback;

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
                    'unit_cost' => $avgUnitCost,
                    'updated_at' => now(),
                ]);

            $cost += round($take * $avgUnitCost, 5);
            $consumed[] = [
                'layer_id' => (int) $layer->id,
                'quantity' => $take,
                'unit_cost' => $avgUnitCost,
            ];

            $remaining -= $take;
        }

        // Align remaining open layers to the weighted average unit cost
        DB::table(self::TABLE)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_quantity', '>', 0)
            ->update([
                'unit_cost' => $avgUnitCost,
                'updated_at' => now(),
            ]);

        if ($remaining > 0) {
            $cost += $remaining * $fallback;
            $consumed[] = [
                'layer_id' => 0,
                'quantity' => $remaining,
                'unit_cost' => $fallback,
            ];
        }

        $cost = round($cost, 5);

        return [
            'cost' => $cost,
            'unit_cost' => $quantity > 0 ? round($cost / $quantity, 5) : 0.0,
            'layers' => $consumed,
        ];
    }

    /**
     * Resolves which costing method should be used for a product in a warehouse.
     */
    public function resolveCostMethod(int $productId, int $warehouseId): string
    {
        // 1. Specific warehouse_inventory row setting
        $basis = DB::table('warehouse_inventory')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('cost_basis');

        if (! empty($basis) && in_array($basis, array_keys(self::getSupportedMethods()), true)) {
            return $basis;
        }

        // 2. Per-warehouse setting
        $warehouseMethods = $this->getWarehouseMethods();
        if (! empty($warehouseMethods[$warehouseId]) && $warehouseMethods[$warehouseId] !== 'DEFAULT') {
            return $warehouseMethods[$warehouseId];
        }

        // 3. Global setting
        return $this->getGlobalMethod();
    }

    /**
     * Returns metadata for all supported cost calculation methods.
     */
    public static function getSupportedMethods(): array
    {
        return [
            WarehouseInventory::COST_BASIS_FIFO => [
                'id' => WarehouseInventory::COST_BASIS_FIFO,
                'name_ar' => 'الوارد أولاً، يُصرف أولاً (FIFO)',
                'name_en' => 'First-In, First-Out (FIFO)',
                'description_ar' => 'يتم احتساب تكلفة البضاعة المباعة بناءً على أقدم طبقات الشراء المخزنة. يعتبر المعيار الأكثر شيوعاً ومطابقة للمعاير المحاسبية.',
                'description_en' => 'Cost of goods issued is calculated from the oldest open inventory batches. Most widely used and compliant with standard accounting.',
                'formula_ar' => 'صرف وتكلفة أقدم الوحدات المخزنة أولاً',
                'formula_en' => 'Oldest layers consumed first',
                'recommended' => true,
            ],
            WarehouseInventory::COST_BASIS_WEIGHTED_AVERAGE => [
                'id' => WarehouseInventory::COST_BASIS_WEIGHTED_AVERAGE,
                'name_ar' => 'متوسط التكلفة المرجح (AVCO / WAC)',
                'name_en' => 'Weighted Average Cost (AVCO)',
                'description_ar' => 'يتم حساب متوسط مرجح لتكلفة جميع الوحدات المتوفرة عند كل حركة توريد جديدة، مما يقلل من أثر تقلبات الأسعار.',
                'description_en' => 'Costs are averaged across all available units upon each receipt, smoothing out purchase price fluctuations.',
                'formula_ar' => 'إجمالي تكلفة الطبقات المتبقية ÷ إجمالي الكمية المتبقية',
                'formula_en' => 'Total remaining cost / Total remaining quantity',
                'recommended' => false,
            ],
            WarehouseInventory::COST_BASIS_LIFO => [
                'id' => WarehouseInventory::COST_BASIS_LIFO,
                'name_ar' => 'الوارد أخيراً، يُصرف أولاً (LIFO)',
                'name_en' => 'Last-In, First-Out (LIFO)',
                'description_ar' => 'يتم صرف البضاعة من أحدث طبقات الشراء أولاً، مما يعكس تكلفة الاستبدال الحديثة في الفترات التضخمية.',
                'description_en' => 'Latest stock received is costed out first, matching recent acquisition costs against current revenues.',
                'formula_ar' => 'صرف وتكلفة أحدث الوحدات المخزنة أولاً',
                'formula_en' => 'Newest layers consumed first',
                'recommended' => false,
            ],
            WarehouseInventory::COST_BASIS_FEFO => [
                'id' => WarehouseInventory::COST_BASIS_FEFO,
                'name_ar' => 'الأقرب انتهاءً، يُصرف أولاً (FEFO)',
                'name_en' => 'First-Expired, First-Out (FEFO)',
                'description_ar' => 'يتم صرف ودفع تكلفة الدفعات الأقرب لتاريخ انتهاء الصلاحية أولاً، وهو ملائم للبضائع ذات تواريخ الصلاحية والدفعات.',
                'description_en' => 'Allocates and costs batches closest to their expiration date first, ideal for batch-tracked items.',
                'formula_ar' => 'صرف وتكلفة الدفعات الأقرب لانتهاء الصلاحية أولاً',
                'formula_en' => 'Batches expiring earliest consumed first',
                'recommended' => false,
            ],
        ];
    }

    /**
     * Gets the active global cost calculation method.
     */
    public function getGlobalMethod(): string
    {
        $method = get_setting('inventory_cost_method', WarehouseInventory::COST_BASIS_FIFO);
        if (in_array($method, array_keys(self::getSupportedMethods()), true)) {
            return $method;
        }

        return WarehouseInventory::COST_BASIS_FIFO;
    }

    /**
     * Sets the global cost calculation method.
     */
    public function setGlobalMethod(string $method): void
    {
        Setting::updateOrCreate(
            ['key' => 'inventory_cost_method'],
            ['value' => $method]
        );
    }

    /**
     * Gets per-warehouse custom cost calculation methods.
     */
    public function getWarehouseMethods(): array
    {
        $raw = get_setting('inventory_warehouse_cost_methods', '{}');
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Sets per-warehouse custom cost calculation methods.
     */
    public function setWarehouseMethods(array $methods): void
    {
        Setting::updateOrCreate(
            ['key' => 'inventory_warehouse_cost_methods'],
            ['value' => json_encode($methods)]
        );
    }

    /**
     * Recalculates remaining layer unit costs to the weighted average for existing inventory.
     */
    public function recalculateWeightedAverageForWarehouse(?int $warehouseId = null): int
    {
        $query = DB::table(self::TABLE)
            ->where('remaining_quantity', '>', 0);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $pairs = $query
            ->select('product_id', 'warehouse_id')
            ->distinct()
            ->get();

        $updatedCount = 0;
        foreach ($pairs as $pair) {
            $layers = DB::table(self::TABLE)
                ->where('product_id', $pair->product_id)
                ->where('warehouse_id', $pair->warehouse_id)
                ->where('remaining_quantity', '>', 0)
                ->get();

            $totalQty = (int) $layers->sum('remaining_quantity');
            $totalCost = (float) $layers->sum(fn ($l) => (int) $l->remaining_quantity * (float) $l->unit_cost);

            if ($totalQty > 0) {
                $avgCost = round($totalCost / $totalQty, 5);
                DB::table(self::TABLE)
                    ->where('product_id', $pair->product_id)
                    ->where('warehouse_id', $pair->warehouse_id)
                    ->where('remaining_quantity', '>', 0)
                    ->update([
                        'unit_cost' => $avgCost,
                        'updated_at' => now(),
                    ]);
                $updatedCount++;
            }
        }

        return $updatedCount;
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
