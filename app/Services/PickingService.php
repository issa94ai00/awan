<?php

namespace App\Services;

use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\WarehouseBin;
use App\Models\WarehouseInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PickingService
{
    /**
     * Create picking list from sales order
     */
    public function createPickingList(SalesOrder $order, $warehouseId = null): PickingList
    {
        $warehouseId = $warehouseId ?? $order->fulfillment_warehouse_id;

        if (!$warehouseId) {
            throw new \Exception('No warehouse specified for picking');
        }

        DB::beginTransaction();

        try {
            $pickingList = PickingList::create([
                'warehouse_id' => $warehouseId,
                'sales_order_id' => $order->id,
                'list_number' => 'PL-' . str_pad(PickingList::count() + 1, 6, '0', STR_PAD_LEFT),
                'priority' => $this->determinePriority($order),
                'status' => PickingList::STATUS_PENDING,
                'total_items' => $order->items->count(),
                'picked_items' => 0,
            ]);

            foreach ($order->items as $orderItem) {
                $bin = $this->findBestBinForItem($orderItem, $warehouseId);

                $pickingListItem = $pickingList->items()->create([
                    'sales_order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'bin_id' => $bin?->id,
                    'quantity_to_pick' => $orderItem->quantity,
                    'quantity_picked' => 0,
                    'status' => PickingListItem::STATUS_PENDING,
                    'sort_order' => $bin?->id ?? 0,
                ]);

                // Generate barcode if product has one
                if ($orderItem->product) {
                    $pickingListItem->barcode = $orderItem->product->barcode;
                    $pickingListItem->save();
                }
            }

            // Optimize picking route
            $this->optimizePickingRoute($pickingList);

            DB::commit();

            return $pickingList;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Determine priority based on order characteristics
     */
    protected function determinePriority(SalesOrder $order): string
    {
        // Urgent if same-day delivery
        if ($order->fulfillment_type === 'delivery' && 
            $order->expected_delivery && 
            $order->expected_delivery->isToday()) {
            return PickingList::PRIORITY_URGENT;
        }

        // High priority for express shipping
        if ($order->shipping_cost > 100) {
            return PickingList::PRIORITY_HIGH;
        }

        // Normal priority for regular orders
        return PickingList::PRIORITY_NORMAL;
    }

    /**
     * Find best bin for picking an item
     */
    protected function findBestBinForItem(SalesOrderItem $orderItem, $warehouseId): ?WarehouseBin
    {
        $productId = $orderItem->product_id;
        $variantId = $orderItem->product_variant_id;
        $quantity = $orderItem->quantity;

        // Find bins with the item and sufficient stock
        $bins = WarehouseBin::active()
            ->where('warehouse_id', $warehouseId)
            ->where('type', WarehouseBin::TYPE_PICKING)
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

        if ($bins->isEmpty()) {
            // Try storage bins if no picking bins available
            $bins = WarehouseBin::active()
                ->where('warehouse_id', $warehouseId)
                ->where('type', WarehouseBin::TYPE_STORAGE)
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

        if ($bins->isEmpty()) {
            return null;
        }

        // Score bins based on multiple factors
        return $bins->map(function ($bin) {
            $score = 0;

            // Factor 1: Bin utilization (prefer less utilized bins)
            $utilization = $bin->getUtilizationPercentageAttribute();
            $score += max(0, 100 - $utilization) * 0.3;

            // Factor 2: Zone proximity (prefer closer zones)
            $zonePriority = match($bin->zone) {
                'A' => 40,
                'B' => 30,
                'C' => 20,
                'D' => 10,
                default => 0,
            };
            $score += $zonePriority;

            // Factor 3: Equipment requirement (prefer bins without equipment)
            if (!$bin->requires_equipment) {
                $score += 30;
            }

            // Factor 4: Stock level (prefer bins with higher stock)
            $inventory = $bin->inventory->first();
            if ($inventory) {
                $score += min(20, $inventory->available_stock);
            }

            return [
                'bin' => $bin,
                'score' => $score,
            ];
        })->sortByDesc('score')->first()['bin'];
    }

    /**
     * Optimize picking route using zone-based sorting
     */
    protected function optimizePickingRoute(PickingList $pickingList): void
    {
        $items = $pickingList->items()->with('bin')->get();

        // Sort by zone, aisle, shelf, level
        $sortedItems = $items->sortBy(function ($item) {
            $bin = $item->bin;
            if (!$bin) {
                return 999;
            }

            $zoneOrder = match($bin->zone) {
                'A' => 1,
                'B' => 2,
                'C' => 3,
                'D' => 4,
                default => 5,
            };

            return $zoneOrder * 1000 + 
                   ($bin->aisle ?? 0) * 100 + 
                   ($bin->shelf ?? 0) * 10 + 
                   ($bin->level ?? 0);
        });

        // Update sort order
        $sortOrder = 0;
        foreach ($sortedItems as $item) {
            $item->sort_order = $sortOrder++;
            $item->save();
        }

        // Store route in picking list
        $route = $sortedItems->map(function ($item) {
            return [
                'bin_id' => $item->bin_id,
                'location_code' => $item->bin?->getLocationCodeAttribute(),
                'product_id' => $item->product_id,
                'quantity' => $item->quantity_to_pick,
            ];
        })->toArray();

        $pickingList->route = $route;
        $pickingList->save();
    }

    /**
     * Start picking process
     */
    public function startPicking(PickingList $pickingList, $pickerId): void
    {
        if (!$pickingList->canStart()) {
            throw new \Exception('Picking list cannot be started');
        }

        $pickingList->start($pickerId);
    }

    /**
     * Pick item from bin
     */
    public function pickItem(PickingListItem $item, $quantity, $verified = false): void
    {
        if ($item->status !== PickingListItem::STATUS_PENDING) {
            throw new \Exception('Item cannot be picked');
        }

        if ($quantity > $item->quantity_to_pick) {
            throw new \Exception('Quantity exceeds required amount');
        }

        $item->markAsPicked($quantity);

        if ($verified) {
            $item->verify();
        }

        // Update picking list progress
        $pickingList = $item->pickingList;
        $pickingList->picked_items = $pickingList->items()->where('status', PickingListItem::STATUS_PICKED)->count();
        $pickingList->save();

        // Update bin utilization
        if ($item->bin) {
            $item->bin->updateUtilization(-$quantity);
        }
    }

    /**
     * Complete picking list
     */
    public function completePicking(PickingList $pickingList): void
    {
        if ($pickingList->status !== PickingList::STATUS_IN_PROGRESS) {
            throw new \Exception('Picking list is not in progress');
        }

        // Check if all items are picked
        $unpickedItems = $pickingList->items()->where('status', PickingListItem::STATUS_PENDING)->count();
        if ($unpickedItems > 0) {
            throw new \Exception("Cannot complete: {$unpickedItems} items remain unpicked");
        }

        $pickingList->complete();
    }

    /**
     * Cancel picking list
     */
    public function cancelPicking(PickingList $pickingList): void
    {
        if ($pickingList->status === PickingList::STATUS_COMPLETED) {
            throw new \Exception('Cannot cancel completed picking list');
        }

        // Restore bin utilization for picked items
        foreach ($pickingList->items as $item) {
            if ($item->status === PickingListItem::STATUS_PICKED && $item->bin) {
                $item->bin->updateUtilization($item->quantity_picked);
            }
        }

        $pickingList->cancel();
    }

    /**
     * Get picking statistics for warehouse
     */
    public function getPickingStatistics($warehouseId, $fromDate = null, $toDate = null): array
    {
        $query = PickingList::where('warehouse_id', $warehouseId);

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $lists = $query->get();

        return [
            'total_lists' => $lists->count(),
            'completed_lists' => $lists->where('status', PickingList::STATUS_COMPLETED)->count(),
            'pending_lists' => $lists->where('status', PickingList::STATUS_PENDING)->count(),
            'in_progress_lists' => $lists->where('status', PickingList::STATUS_IN_PROGRESS)->count(),
            'total_items_picked' => $lists->sum('picked_items'),
            'average_completion_time' => $this->calculateAverageCompletionTime($lists),
            'picker_performance' => $this->getPickerPerformance($lists),
        ];
    }

    /**
     * Calculate average completion time
     */
    protected function calculateAverageCompletionTime(Collection $lists): float
    {
        $completedLists = $lists->where('status', PickingList::STATUS_COMPLETED);

        if ($completedLists->isEmpty()) {
            return 0;
        }

        $totalMinutes = $completedLists->sum(function ($list) {
            if (!$list->started_at || !$list->completed_at) {
                return 0;
            }
            return $list->started_at->diffInMinutes($list->completed_at);
        });

        return $totalMinutes / $completedLists->count();
    }

    /**
     * Get picker performance metrics
     */
    protected function getPickerPerformance(Collection $lists): Collection
    {
        return $lists->whereNotNull('picker_id')
            ->groupBy('picker_id')
            ->map(function ($pickerLists) {
                $completed = $pickerLists->where('status', PickingList::STATUS_COMPLETED);
                $totalItems = $pickerLists->sum('total_items');
                $pickedItems = $pickerLists->sum('picked_items');

                return [
                    'picker_id' => $pickerLists->first()->picker_id,
                    'total_lists' => $pickerLists->count(),
                    'completed_lists' => $completed->count(),
                    'total_items' => $totalItems,
                    'picked_items' => $pickedItems,
                    'completion_rate' => $totalItems > 0 ? ($pickedItems / $totalItems) * 100 : 0,
                ];
            })
            ->sortByDesc('completion_rate');
    }

    /**
     * Suggest optimal picking order for multiple orders
     */
    public function suggestBatchPicking(array $orderIds, $warehouseId): array
    {
        $orders = SalesOrder::with('items')->whereIn('id', $orderIds)->get();

        if ($orders->isEmpty()) {
            return [];
        }

        // Group items by bin
        $binGroups = collect();

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $bin = $this->findBestBinForItem($item, $warehouseId);
                if ($bin) {
                    $binKey = $bin->id;
                    if (!$binGroups->has($binKey)) {
                        $binGroups->put($binKey, [
                            'bin' => $bin,
                            'items' => collect(),
                        ]);
                    }
                    $binGroups[$binKey]['items']->push([
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                    ]);
                }
            }
        }

        // Sort bins by location
        $sortedBins = $binGroups->sortBy(function ($group) {
            $bin = $group['bin'];
            $zoneOrder = match($bin->zone) {
                'A' => 1,
                'B' => 2,
                'C' => 3,
                'D' => 4,
                default => 5,
            };
            return $zoneOrder * 1000 + ($bin->aisle ?? 0) * 100 + ($bin->shelf ?? 0) * 10;
        });

        return $sortedBins->values()->toArray();
    }
}
