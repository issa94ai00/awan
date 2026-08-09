<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderAllocationService
{
    protected InventoryAllocationService $inventoryAllocation;

    public function __construct(InventoryAllocationService $inventoryAllocation)
    {
        $this->inventoryAllocation = $inventoryAllocation;
    }

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

    /**
     * Allocate inventory for a sales order
     */
    public function allocateOrder(SalesOrder $order): Collection
    {
        $allocations = collect();

        foreach ($order->items as $item) {
            $allocation = $this->allocateOrderItem($item, $order);
            $allocations->push($allocation);
        }

        return $allocations;
    }

    /**
     * Allocate inventory for a single order item
     */
    public function allocateOrderItem(SalesOrderItem $item, SalesOrder $order): array
    {
        $productId = $item->product_id;
        $variantId = $item->product_variant_id;
        $quantity = $item->quantity;
        $preferredWarehouseId = $order->fulfillment_warehouse_id;

        // If preferred warehouse is specified, try to allocate from it
        if ($preferredWarehouseId) {
            if ($this->inventoryAllocation->checkAvailability($productId, $quantity, $preferredWarehouseId, $variantId)) {
                $allocation = $this->inventoryAllocation->allocate($productId, $quantity, $preferredWarehouseId, $variantId);
                return [
                    'item_id' => $item->id,
                    'warehouse_id' => $preferredWarehouseId,
                    'quantity' => $quantity,
                    'allocation' => $allocation,
                    'status' => 'fulfilled',
                ];
            }
        }

        // Find best warehouse for allocation
        $bestWarehouse = $this->findBestWarehouse($productId, $quantity, $variantId, $order->channel_id);

        if ($bestWarehouse) {
            $allocation = $this->inventoryAllocation->allocate($productId, $quantity, $bestWarehouse->id, $variantId);
            return [
                'item_id' => $item->id,
                'warehouse_id' => $bestWarehouse->id,
                'quantity' => $quantity,
                'allocation' => $allocation,
                'status' => 'fulfilled',
            ];
        }

        // Try to split across multiple warehouses
        $splitAllocation = $this->allocateAcrossWarehouses($productId, $quantity, $variantId);

        if ($splitAllocation) {
            return [
                'item_id' => $item->id,
                'warehouse_id' => null,
                'quantity' => $quantity,
                'allocation' => $splitAllocation,
                'status' => 'split',
            ];
        }

        return [
            'item_id' => $item->id,
            'warehouse_id' => null,
            'quantity' => 0,
            'allocation' => collect(),
            'status' => 'backordered',
        ];
    }

    /**
     * Find the best warehouse for allocation based on multiple factors
     */
    protected function findBestWarehouse(int $productId, int $quantity, ?int $variantId, ?int $channelId): ?Warehouse
    {
        $availableWarehouses = $this->getAvailableWarehouses($productId, $quantity, $variantId);

        if ($availableWarehouses->isEmpty()) {
            return null;
        }

        // Score each warehouse based on multiple factors
        return $availableWarehouses->map(function ($warehouse) use ($channelId) {
            $score = 0;

            // Factor 1: Proximity to channel (if channel has preferred warehouses)
            if ($channelId) {
                $channel = \App\Models\OrderChannel::find($channelId);
                if ($channel && $channel->getConfigValue('preferred_warehouse_id') == $warehouse->id) {
                    $score += 50;
                }
            }

            // Factor 2: Utilization (prefer less utilized warehouses)
            $utilization = $warehouse->getUtilizationPercentage();
            $score += max(0, 100 - $utilization) * 0.3;

            // Factor 3: Stock level (prefer warehouses with higher stock)
            $inventory = $warehouse->inventory->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->first();
            if ($inventory) {
                $score += min(100, $inventory->available_stock) * 0.2;
            }

            // Factor 4: Location type priority
            $locationPriority = match($warehouse->location_type) {
                'distribution_center' => 30,
                'warehouse' => 20,
                'branch' => 10,
                '3pl' => 5,
                default => 0,
            };
            $score += $locationPriority;

            return [
                'warehouse' => $warehouse,
                'score' => $score,
            ];
        })->sortByDesc('score')->first()['warehouse'] ?? null;
    }

    /**
     * Get warehouses with available stock for a product
     */
    protected function getAvailableWarehouses(int $productId, int $quantity, ?int $variantId): Collection
    {
        return Warehouse::active()
            ->whereHas('inventory', function ($query) use ($productId, $variantId, $quantity) {
                $query->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->where('available_stock', '>=', $quantity);
            })
            ->with(['inventory' => function ($query) use ($productId, $variantId) {
                $query->where('product_id', $productId)
                    ->where('product_variant_id', $variantId);
            }])
            ->get();
    }

    /**
     * Allocate quantity across multiple warehouses
     */
    protected function allocateAcrossWarehouses(int $productId, int $quantity, ?int $variantId): ?Collection
    {
        $allocations = collect();
        $remaining = $quantity;

        // Get all warehouses with available stock
        $warehouses = Warehouse::active()
            ->whereHas('inventory', function ($query) use ($productId, $variantId) {
                $query->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->where('available_stock', '>', 0);
            })
            ->with(['inventory' => function ($query) use ($productId, $variantId) {
                $query->where('product_id', $productId)
                    ->where('product_variant_id', $variantId);
            }])
            ->get()
            ->sortByDesc(function ($warehouse) use ($productId, $variantId) {
                return $warehouse->inventory->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->first()?->available_stock ?? 0;
            });

        foreach ($warehouses as $warehouse) {
            if ($remaining <= 0) {
                break;
            }

            $inventory = $warehouse->inventory->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->first();

            if (!$inventory || $inventory->available_stock <= 0) {
                continue;
            }

            $allocateQty = min($remaining, $inventory->available_stock);
            $allocation = $this->inventoryAllocation->allocate($productId, $allocateQty, $warehouse->id, $variantId);

            $allocations->push([
                'warehouse_id' => $warehouse->id,
                'quantity' => $allocateQty,
                'allocation' => $allocation,
            ]);

            $remaining -= $allocateQty;
        }

        return $remaining > 0 ? null : $allocations;
    }

    /**
     * Check if an order can be fully fulfilled
     */
    public function canFulfillOrder(SalesOrder $order): bool
    {
        foreach ($order->items as $item) {
            $canFulfill = $this->canFulfillItem($item, $order);
            if (!$canFulfill) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a single item can be fulfilled
     */
    public function canFulfillItem(SalesOrderItem $item, SalesOrder $order): bool
    {
        $productId = $item->product_id;
        $variantId = $item->product_variant_id;
        $quantity = $item->quantity;
        $preferredWarehouseId = $order->fulfillment_warehouse_id;

        // Check preferred warehouse first
        if ($preferredWarehouseId) {
            if ($this->inventoryAllocation->checkAvailability($productId, $quantity, $preferredWarehouseId, $variantId)) {
                return true;
            }
        }

        // Check if any warehouse has enough stock
        $bestWarehouse = $this->findBestWarehouse($productId, $quantity, $variantId, $order->channel_id);
        if ($bestWarehouse) {
            return true;
        }

        // Check if split allocation is possible
        $totalAvailable = WarehouseInventory::where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->whereHas('warehouse', fn($q) => $q->where('is_active', true))
            ->sum('available_stock');

        return $totalAvailable >= $quantity;
    }

    /**
     * Release allocated inventory for an order
     */
    public function releaseOrderAllocation(SalesOrder $order): void
    {
        foreach ($order->items as $item) {
            $this->inventoryAllocation->release(
                $item->product_id,
                $item->quantity,
                $order->fulfillment_warehouse_id,
                $item->product_variant_id
            );
        }
    }

    /**
     * Get fulfillment summary for an order
     */
    public function getFulfillmentSummary(SalesOrder $order): array
    {
        $summary = [
            'total_items' => $order->items->count(),
            'fulfilled_items' => 0,
            'backordered_items' => 0,
            'split_items' => 0,
            'warehouses_used' => collect(),
            'estimated_delivery' => $order->estimated_delivery,
        ];

        foreach ($order->items as $item) {
            $allocation = $this->allocateOrderItem($item, $order);
            
            match($allocation['status']) {
                'fulfilled' => $summary['fulfilled_items']++,
                'backordered' => $summary['backordered_items']++,
                'split' => $summary['split_items']++,
            };

            if ($allocation['warehouse_id']) {
                $summary['warehouses_used']->push($allocation['warehouse_id']);
            }
        }

        $summary['warehouses_used'] = $summary['warehouses_used']->unique()->values();
        $summary['fulfillment_percentage'] = ($summary['fulfilled_items'] / $summary['total_items']) * 100;

        return $summary;
    }

    /**
     * Auto-select fulfillment warehouse based on customer location
     */
    /**
     * Where an order should be served from.
     *
     * Everything below the first `return` used to be unreachable — a geolocation
     * search over an undefined `$warehouses`, which would have been a fatal error
     * had it ever run. It has been removed rather than fixed: routing now goes
     * through SalesOrderWorkflowService::pickWarehouse, which scores warehouses
     * by whether they can actually cover the order's lines, and there is no
     * second, quietly different answer to the same question.
     */
    public function selectFulfillmentWarehouse(SalesOrder $order): ?int
    {
        if ($order->assignedEmployee?->warehouse_id) {
            return $order->assignedEmployee->warehouse_id;
        }

        return Warehouse::active()->orderBy('id')->value('id');
    }
}
