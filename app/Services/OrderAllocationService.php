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
    public function selectFulfillmentWarehouse(SalesOrder $order): ?int
    {
        if (!$order->customer || !$order->customer->address) {
            return null;
        }

        // Find closest warehouse to customer
        $warehouses = Warehouse::active()->get();

        if ($warehouses->isEmpty()) {
            return null;
        }

        // Simple distance calculation (in production, use proper geolocation)
        $closestWarehouse = $warehouses->first();
        $minDistance = PHP_FLOAT_MAX;

        foreach ($warehouses as $warehouse) {
            if (!$warehouse->latitude || !$warehouse->longitude) {
                continue;
            }

            $distance = $this->calculateDistance(
                $order->customer->address['latitude'] ?? 0,
                $order->customer->address['longitude'] ?? 0,
                $warehouse->latitude,
                $warehouse->longitude
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestWarehouse = $warehouse;
            }
        }

        return $closestWarehouse?->id;
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     */
    protected function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
