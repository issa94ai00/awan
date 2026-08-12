<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;

/**
 * Suggests how an order's lines should be split across warehouses.
 *
 * Purely advisory: nothing here reserves, ships or posts. The suggestion is a
 * plan the operator confirms and may edit, and it becomes real only when the
 * order is confirmed through SalesOrderWorkflowService.
 *
 * The InventoryAllocationService dependency this class used to carry went with
 * the lifecycle methods that needed it — see the note further down.
 */
class OrderAllocationService
{
    /**
     * Suggest how an order's lines should be split across warehouses.
     *
     * This is the "اقتراح تلقائي" contract the mobile app calls when staff
     * build an internal order: for every requested line it returns the greedy
     * warehouse split. The main (primary) warehouse is tried first; the rest
     * of the quantity falls through to warehouses ordered by available stock.
     *
     * No stock is reserved here — the result is a plan the caller confirms
     * (and may edit) before the order is persisted as allocations.
     *
     * @param  array<int, array{product_id: int, quantity: int, product_variant_id?: ?int}>  $items
     * @return array<int, array<string, mixed>>
     */
    public function suggestAllocations(array $items): array
    {
        $productIds = collect($items)->pluck('product_id')->unique()->values();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $suggestions = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variantId = $item['product_variant_id'] ?? null;
            $quantity = max(1, (int) $item['quantity']);

            $product = $products->get($productId);
            $warehouses = $this->availableWarehousesFor($productId, $variantId);

            $remaining = $quantity;
            $allocations = [];
            foreach ($warehouses as $warehouse) {
                if ($remaining <= 0) {
                    break;
                }
                $available = (int) $warehouse['available_stock'];
                if ($available <= 0) {
                    continue;
                }
                $take = min($remaining, $available);
                $allocations[] = [
                    'warehouse_id' => $warehouse['warehouse']->id,
                    'warehouse_name' => $warehouse['warehouse']->name,
                    'warehouse_code' => $warehouse['warehouse']->code,
                    'is_primary' => (bool) $warehouse['warehouse']->is_primary,
                    'quantity' => $take,
                    'available_stock' => $available,
                ];
                $remaining -= $take;
            }

            $suggestions[] = [
                'product_id' => $productId,
                'product_name' => $product ? ($product->name_ar ?? $product->name_en ?? '') : '',
                'quantity' => $quantity,
                'fulfilled' => $remaining === 0,
                'shortage' => $remaining,
                'allocations' => $allocations,
            ];
        }

        return $suggestions;
    }

    /**
     * Active warehouses holding stock for a product, ranked main-first then
     * by available stock. Values are read through the `available_stock`
     * accessor so reserved/damaged/quarantined quantities are excluded.
     *
     * @return array<int, array{warehouse: Warehouse, available_stock: int}>
     */
    protected function availableWarehousesFor(int $productId, ?int $variantId): array
    {
        $inventories = WarehouseInventory::where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true))
            ->with('warehouse')
            ->get()
            ->filter(fn ($inv) => $inv->available_stock > 0);

        return $inventories
            ->map(fn ($inv) => [
                'warehouse' => $inv->warehouse,
                'available_stock' => $inv->available_stock,
            ])
            ->sortBy([
                ['warehouse.is_primary', 'desc'],
                ['available_stock', 'desc'],
                ['warehouse.id', 'asc'],
            ])
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ *
     * What used to live here
     * ------------------------------------------------------------------ *
     *
     * `allocateOrder`, `allocateOrderItem`, `findBestWarehouse`,
     * `getAvailableWarehouses`, `allocateAcrossWarehouses`, `canFulfillOrder`,
     * `canFulfillItem`, `getFulfillmentSummary` and `releaseOrderAllocation`
     * have been removed rather than repaired.
     *
     * They were a second implementation of the order lifecycle, and it disagreed
     * with the real one on every point that matters. It reserved through a
     * separate batch/serial mechanism instead of `warehouse_inventory`, ignored
     * the per-warehouse split recorded on the order's own lines, and left the
     * order at "processing" with no invoice, no journal entry and no picking
     * list — so an order routed through it never reached the books.
     *
     * They could not have worked in any case: `getAvailableWarehouses`,
     * `allocateAcrossWarehouses` and `canFulfillItem` filtered on
     * `available_stock`, which is a model accessor and not a column, so every
     * one of them raised "Unknown column 'available_stock'" on contact.
     *
     * Allocation, coverage and release now belong to SalesOrderWorkflowService,
     * which measures each line against the warehouses it is actually planned to
     * come from. What remains below is the suggestion engine — a plan the caller
     * confirms — which reserves nothing and is the one part of this class the
     * rest of the system depends on.
     */

    /**
     * Where an order should be served from, as far as anyone can tell at the
     * moment it is raised.
     *
     * Everything below the first `return` used to be unreachable — a geolocation
     * search over an undefined `$warehouses`, which would have been a fatal error
     * had it ever run. It has been removed rather than fixed: routing now goes
     * through SalesOrderWorkflowService, which measures warehouses against the
     * lines they would have to fill, and there is no second, quietly different
     * answer to the same question.
     *
     * The old fallback to `Warehouse::active()->orderBy('id')` — the main
     * warehouse — went with it. Naming a warehouse here settled the routing
     * before the stock had been looked at, and confirmation, finding the order
     * already routed, never reconsidered it. `null` means "nobody has decided
     * yet", which is the truth, and confirmation decides.
     */
    public function selectFulfillmentWarehouse(SalesOrder $order): ?int
    {
        return $order->assignedEmployee?->warehouse_id;
    }
}
