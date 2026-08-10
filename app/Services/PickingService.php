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
     * Picking instructions for an order, one per warehouse it is routed to.
     *
     * A picking list is an instruction to a *building*: go to these shelves and
     * take these goods off them. An order split across two warehouses therefore
     * needs two, each naming only its own share.
     *
     * Previously one list was raised at the order's fulfilment warehouse for the
     * whole quantity. On a split order that was wrong twice over: the branch was
     * told to pick eight units when only three were routed to it and only three
     * were held there, and the warehouse supplying the other five was never told
     * anything at all.
     *
     * @param  array<int,array<int,int>>  $plan  warehouse id => [order item id => quantity]
     * @return array<int,PickingList>  keyed by warehouse id
     */
    public function createPickingListsForPlan(SalesOrder $order, array $plan): array
    {
        $order->loadMissing('items.product');

        $existing = $this->activePickingLists($order)->keyBy('warehouse_id');
        $lists = [];

        foreach ($plan as $warehouseId => $quantities) {
            $warehouseId = (int) $warehouseId;

            // One live list per warehouse. A re-confirm or a repair run must not
            // leave a building holding two instructions for the same units.
            if ($existing->has($warehouseId)) {
                $lists[$warehouseId] = $existing[$warehouseId];
                continue;
            }

            $quantities = array_filter($quantities, fn ($q) => (int) $q > 0);

            if ($quantities === []) {
                continue;
            }

            $lists[$warehouseId] = $this->buildList($order, $warehouseId, $quantities);
        }

        return $lists;
    }

    /**
     * The whole order picked from one warehouse — the single-source case.
     *
     * Kept for callers that have no routing to speak of; it delegates so the
     * building of a list lives in one place.
     */
    public function createPickingList(SalesOrder $order, $warehouseId = null): PickingList
    {
        $warehouseId = (int) ($warehouseId ?? $order->fulfillment_warehouse_id);

        if (!$warehouseId) {
            throw new \Exception('No warehouse specified for picking');
        }

        $order->loadMissing('items');

        $lists = $this->createPickingListsForPlan($order, [
            $warehouseId => $order->items->pluck('quantity', 'id')->map(fn ($q) => (int) $q)->all(),
        ]);

        return $lists[$warehouseId]
            ?? $this->activePickingLists($order)->first()
            ?? throw new \Exception('Could not raise a picking list for this order');
    }

    /** Writes one list and its lines. */
    private function buildList(SalesOrder $order, int $warehouseId, array $quantities): PickingList
    {
        return DB::transaction(function () use ($order, $warehouseId, $quantities) {
            $pickingList = PickingList::create([
                'warehouse_id' => $warehouseId,
                'sales_order_id' => $order->id,
                // Derived from the last id: counting reuses a number the moment
                // any list is deleted, and two concurrent confirmations collide.
                'list_number' => 'PL-' . str_pad((string) (((int) PickingList::max('id')) + 1), 6, '0', STR_PAD_LEFT),
                'priority' => $this->determinePriority($order),
                'status' => PickingList::STATUS_PENDING,
                'total_items' => count($quantities),
                'picked_items' => 0,
            ]);

            foreach ($order->items as $orderItem) {
                $quantity = (int) ($quantities[$orderItem->id] ?? 0);

                // A line this warehouse supplies nothing of does not belong on
                // its list — printing it would send a picker looking for goods
                // that were never routed here.
                if ($quantity <= 0) {
                    continue;
                }

                $bin = $this->findBestBinForItem($orderItem, $warehouseId);

                $pickingListItem = $pickingList->items()->create([
                    'sales_order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'bin_id' => $bin?->id,
                    'quantity_to_pick' => $quantity,
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

            $this->optimizePickingRoute($pickingList);

            return $pickingList;
        });
    }

    /**
     * Every live picking list for the order — one per warehouse it is routed to.
     *
     * A cancelled list does not count; that is what makes re-picking a revived
     * order possible.
     *
     * @return Collection<int,PickingList>
     */
    public function activePickingLists(SalesOrder $order): Collection
    {
        return PickingList::where('sales_order_id', $order->id)
            ->where('status', '!=', PickingList::STATUS_CANCELLED)
            ->orderBy('id')
            ->get();
    }

    /**
     * One live picking list, for callers that only need to know whether the
     * order has been given to a warehouse at all.
     */
    public function activePickingList(SalesOrder $order): ?PickingList
    {
        return $this->activePickingLists($order)->last();
    }

    /**
     * Brings the order's picking list to completion as the goods ship.
     *
     * Shipping is the moment the stock actually leaves, so leaving the picking
     * list behind at "pending" would say nobody ever fetched goods that are
     * demonstrably gone. Any line still awaiting a pick is recorded at its
     * required quantity — the shipment is the evidence it was fetched.
     *
     * Deliberately moves no stock. `SalesOrderWorkflowService::applyShipment`
     * is the single place inventory leaves for a sale, under an idempotent key;
     * issuing here as well would deduct every unit twice.
     *
     * @throws \Exception when the picking record contradicts what is shipping
     */
    public function completeForShipment(SalesOrder $order): ?PickingList
    {
        // Every warehouse the order was routed to has its own list, and the
        // shipment is only honest once all of them are accounted for. Completing
        // just one left the other building's instruction open for goods that had
        // demonstrably left it.
        $completed = null;

        foreach ($this->activePickingLists($order) as $list) {
            $completed = $this->completeList($list) ?? $completed;
        }

        return $completed;
    }

    /** @throws \Exception when the picking record contradicts what is shipping */
    private function completeList(PickingList $list): ?PickingList
    {
        if ($list->status === PickingList::STATUS_COMPLETED) {
            return $list;
        }

        $list->load('items.product');

        // A short line means the shelf held fewer units than the order wants.
        // Shipping the full quantity anyway would move stock that was never
        // found — exactly the contradiction this guard exists to prevent.
        $short = $list->items->filter(fn ($i) => $i->status === PickingListItem::STATUS_SHORT);

        if ($short->isNotEmpty()) {
            throw new \Exception(sprintf(
                'قائمة التجهيز %s تسجّل نقصاً في %s. صحّح الكميات أو عدّل الطلب قبل الشحن، وإلا خرج من المخزون ما لم يُسحب فعلياً.',
                $list->list_number,
                $short->take(3)->map(fn ($i) => sprintf(
                    '"%s" (سُحب %d من %d)',
                    $i->product->name_ar ?? $i->product->name_en ?? ('#' . $i->product_id),
                    $i->quantity_picked,
                    $i->quantity_to_pick
                ))->implode('، ') . ($short->count() > 3 ? ' وغيرها' : '')
            ));
        }

        if ($list->status === PickingList::STATUS_CANCELLED) {
            throw new \Exception('قائمة التجهيز ' . $list->list_number . ' ملغاة — لا يمكن شحن طلب بلا تجهيز.');
        }

        // Starting stamps the picker and the time; a list that ships without it
        // would carry no record of who fetched the goods.
        if ($list->status === PickingList::STATUS_PENDING) {
            $list->start(auth()->id());
        }

        foreach ($list->items as $item) {
            if ($item->status === PickingListItem::STATUS_PENDING) {
                $item->markAsPicked($item->quantity_to_pick);
            }
        }

        $list->picked_items = $list->items()->whereIn('status', [PickingListItem::STATUS_PICKED, PickingListItem::STATUS_SHORT])->count();
        $list->save();

        $list->complete();

        return $list;
    }

    /**
     * Stands the picking list down when its order is cancelled. A list left
     * pending would send someone to pick goods for a sale that is off.
     */
    public function cancelForOrder(SalesOrder $order): ?PickingList
    {
        $cancelled = null;

        // Stood down at every warehouse the order reached. Cancelling only one
        // would leave the other still sending someone to the shelves for a sale
        // that is off.
        foreach ($this->activePickingLists($order) as $list) {
            // Once picking is finished the goods have already been taken off the
            // shelf; cancelling the paperwork then would hide that it happened.
            if ($list->status === PickingList::STATUS_COMPLETED) {
                continue;
            }

            $list->cancel();
            $cancelled = $list;
        }

        return $cancelled;
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

        // This previously filtered `warehouse_bins` on `is_active`, `type` and
        // `requires_equipment` — none of which are columns on that table — so
        // every call threw and took the whole picking list down with it.
        //
        // Bin mapping is optional here: none are configured today, and
        // `picking_list_items.bin_id` is nullable for exactly that reason. A
        // warehouse without bins should get a picking list without bin hints,
        // not no picking list at all.
        return WarehouseBin::query()
            ->where('warehouse_id', $warehouseId)
            ->whereExists(function ($q) use ($productId, $quantity) {
                $q->select(DB::raw(1))
                    ->from('warehouse_inventory')
                    ->whereColumn('warehouse_inventory.bin_id', 'warehouse_bins.id')
                    ->where('warehouse_inventory.product_id', $productId)
                    ->whereRaw('warehouse_inventory.quantity - warehouse_inventory.reserved_quantity >= ?', [$quantity]);
            })
            // Walking order: nearest zone first, then along the rack and up the
            // shelf — the same sequence optimizePickingRoute sorts the list by.
            ->orderBy('zone')
            ->orderBy('rack')
            ->orderBy('shelf')
            ->first();
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
        $list = $item->pickingList()->first();

        // The line's own status said nothing about whether picking had begun,
        // so quantities could be recorded against a list nobody had started —
        // or one already completed or cancelled. Starting is what assigns the
        // picker, and without it a pick has no one's name against it.
        if (!$list || $list->status !== PickingList::STATUS_IN_PROGRESS) {
            throw new \Exception('لا يمكن تسجيل السحب قبل بدء التجهيز. ابدأ القائمة أولاً.');
        }

        if ($item->status !== PickingListItem::STATUS_PENDING) {
            throw new \Exception('هذا الصنف سُجّل سحبه مسبقاً.');
        }

        if ($quantity > $item->quantity_to_pick) {
            throw new \Exception('الكمية المسحوبة تتجاوز المطلوب (' . $item->quantity_to_pick . ').');
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
