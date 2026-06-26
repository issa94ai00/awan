<?php

namespace App\Services;

use App\Models\PackingList;
use App\Models\PackingListItem;
use App\Models\PickingList;
use App\Models\ShippingManifest;
use App\Models\ShippingManifestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PackingService
{
    /**
     * Create packing list from picking list
     */
    public function createPackingList(PickingList $pickingList): PackingList
    {
        if (!$pickingList->isCompleted()) {
            throw new \Exception('Picking list must be completed before packing');
        }

        DB::beginTransaction();

        try {
            $packingList = PackingList::create([
                'warehouse_id' => $pickingList->warehouse_id,
                'picking_list_id' => $pickingList->id,
                'sales_order_id' => $pickingList->sales_order_id,
                'list_number' => 'PCK-' . str_pad(PackingList::count() + 1, 6, '0', STR_PAD_LEFT),
                'status' => PackingList::STATUS_PENDING,
                'total_packages' => 0,
                'total_weight' => 0,
            ]);

            // Create packing list items from picked items
            foreach ($pickingList->items as $pickingItem) {
                if ($pickingItem->status === PickingListItem::STATUS_PICKED) {
                    $packingListItem = $packingList->items()->create([
                        'picking_list_item_id' => $pickingItem->id,
                        'product_id' => $pickingItem->product_id,
                        'product_variant_id' => $pickingItem->product_variant_id,
                        'quantity' => $pickingItem->quantity_picked,
                        'package_number' => $this->generatePackageNumber($packingList),
                        'fragile' => $pickingItem->product?->is_fragile ?? false,
                    ]);

                    // Calculate item weight if product has weight
                    if ($pickingItem->product) {
                        $packingListItem->weight = $pickingItem->product->weight * $pickingItem->quantity_picked;
                        $packingListItem->dimensions = $pickingItem->product->dimensions ?? null;
                        $packingListItem->save();
                    }
                }
            }

            // Calculate totals
            $this->calculatePackingTotals($packingList);

            DB::commit();

            return $packingList;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate package number
     */
    protected function generatePackageNumber(PackingList $packingList): string
    {
        $count = $packingList->items()->count() + 1;
        return "PKG-{$count}";
    }

    /**
     * Calculate packing totals
     */
    protected function calculatePackingTotals(PackingList $packingList): void
    {
        $items = $packingList->items;
        
        $packingList->total_packages = $items->count();
        $packingList->total_weight = $items->sum('weight');
        
        // Calculate total dimensions
        $totalDimensions = [
            'length' => 0,
            'width' => 0,
            'height' => 0,
        ];

        foreach ($items as $item) {
            if ($item->dimensions) {
                $totalDimensions['length'] += $item->dimensions['length'] ?? 0;
                $totalDimensions['width'] = max($totalDimensions['width'], $item->dimensions['width'] ?? 0);
                $totalDimensions['height'] += $item->dimensions['height'] ?? 0;
            }
        }

        $packingList->dimensions = $totalDimensions;
        $packingList->save();
    }

    /**
     * Start packing process
     */
    public function startPacking(PackingList $packingList, $packerId): void
    {
        if (!$packingList->canStart()) {
            throw new \Exception('Packing list cannot be started');
        }

        $packingList->start($packerId);
    }

    /**
     * Update packing item with package details
     */
    public function updatePackageDetails(PackingListItem $item, array $details): void
    {
        $item->update([
            'package_number' => $details['package_number'] ?? $item->package_number,
            'dimensions' => $details['dimensions'] ?? $item->dimensions,
            'weight' => $details['weight'] ?? $item->weight,
            'fragile' => $details['fragile'] ?? $item->fragile,
            'notes' => $details['notes'] ?? $item->notes,
        ]);

        // Recalculate totals
        $this->calculatePackingTotals($item->packingList);
    }

    /**
     * Complete packing list
     */
    public function completePacking(PackingList $packingList): void
    {
        if ($packingList->status !== PackingList::STATUS_IN_PROGRESS) {
            throw new \Exception('Packing list is not in progress');
        }

        // Validate all items are packed
        if ($packingList->items->isEmpty()) {
            throw new \Exception('No items in packing list');
        }

        $packingList->complete();
    }

    /**
     * Cancel packing list
     */
    public function cancelPacking(PackingList $packingList): void
    {
        if ($packingList->status === PackingList::STATUS_COMPLETED) {
            throw new \Exception('Cannot cancel completed packing list');
        }

        $packingList->cancel();
    }

    /**
     * Suggest optimal box type based on items
     */
    public function suggestBoxType(PackingList $packingList): string
    {
        $totalVolume = $packingList->items->sum(function ($item) {
            return $item->getVolumeAttribute();
        });

        $totalWeight = $packingList->total_weight;

        // Box type suggestions based on volume and weight
        if ($totalVolume < 1000 && $totalWeight < 1) {
            return 'small';
        } elseif ($totalVolume < 5000 && $totalWeight < 5) {
            return 'medium';
        } elseif ($totalVolume < 15000 && $totalWeight < 15) {
            return 'large';
        } else {
            return 'pallet';
        }
    }

    /**
     * Create shipping manifest from packing lists
     */
    public function createShippingManifest(array $packingListIds, $carrierId = null, $carrierName = null): ShippingManifest
    {
        $packingLists = PackingList::with(['salesOrder', 'items'])
            ->whereIn('id', $packingListIds)
            ->where('status', PackingList::STATUS_COMPLETED)
            ->get();

        if ($packingLists->isEmpty()) {
            throw new \Exception('No completed packing lists found');
        }

        $warehouseId = $packingLists->first()->warehouse_id;

        DB::beginTransaction();

        try {
            $manifest = ShippingManifest::create([
                'warehouse_id' => $warehouseId,
                'manifest_number' => 'SHP-' . str_pad(ShippingManifest::count() + 1, 6, '0', STR_PAD_LEFT),
                'carrier_id' => $carrierId,
                'carrier_name' => $carrierName,
                'status' => ShippingManifest::STATUS_PENDING,
                'shipping_date' => now(),
                'total_packages' => 0,
                'total_weight' => 0,
                'shipping_cost' => 0,
            ]);

            foreach ($packingLists as $packingList) {
                foreach ($packingList->items as $packingItem) {
                    $manifestItem = $manifest->items()->create([
                        'packing_list_id' => $packingList->id,
                        'sales_order_id' => $packingList->sales_order_id,
                        'package_number' => $packingItem->package_number,
                        'weight' => $packingItem->weight,
                        'dimensions' => $packingItem->dimensions,
                        'delivery_address' => $packingList->salesOrder?->shipping_address,
                        'recipient_name' => $packingList->salesOrder?->customer?->name,
                        'recipient_phone' => $packingList->salesOrder?->customer?->phone,
                        'delivery_status' => ShippingManifestItem::DELIVERY_STATUS_PENDING,
                    ]);
                }
            }

            // Calculate totals
            $manifest->total_packages = $manifest->items->count();
            $manifest->total_weight = $manifest->items->sum('weight');
            $manifest->save();

            DB::commit();

            return $manifest;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark manifest as in transit
     */
    public function dispatchManifest(ShippingManifest $manifest): void
    {
        if ($manifest->status !== ShippingManifest::STATUS_PENDING) {
            throw new \Exception('Manifest is not pending');
        }

        $manifest->markAsInTransit();

        // Update all items to in transit
        $manifest->items()->update([
            'delivery_status' => ShippingManifestItem::DELIVERY_STATUS_IN_TRANSIT,
        ]);
    }

    /**
     * Mark manifest item as delivered
     */
    public function markItemDelivered(ShippingManifestItem $item, $signature = null): void
    {
        $item->markAsDelivered($signature);

        // Check if all items are delivered
        $manifest = $item->shippingManifest;
        $allDelivered = $manifest->items()->where('delivery_status', '!=', ShippingManifestItem::DELIVERY_STATUS_DELIVERED)->count() === 0;

        if ($allDelivered) {
            $manifest->markAsDelivered();
        }
    }

    /**
     * Get packing statistics for warehouse
     */
    public function getPackingStatistics($warehouseId, $fromDate = null, $toDate = null): array
    {
        $query = PackingList::where('warehouse_id', $warehouseId);

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $lists = $query->get();

        return [
            'total_lists' => $lists->count(),
            'completed_lists' => $lists->where('status', PackingList::STATUS_COMPLETED)->count(),
            'pending_lists' => $lists->where('status', PackingList::STATUS_PENDING)->count(),
            'in_progress_lists' => $lists->where('status', PackingList::STATUS_IN_PROGRESS)->count(),
            'total_packages' => $lists->sum('total_packages'),
            'total_weight' => $lists->sum('total_weight'),
            'average_completion_time' => $this->calculateAverageCompletionTime($lists),
            'packer_performance' => $this->getPackerPerformance($lists),
        ];
    }

    /**
     * Calculate average completion time
     */
    protected function calculateAverageCompletionTime(Collection $lists): float
    {
        $completedLists = $lists->where('status', PackingList::STATUS_COMPLETED);

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
     * Get packer performance metrics
     */
    protected function getPackerPerformance(Collection $lists): Collection
    {
        return $lists->whereNotNull('packer_id')
            ->groupBy('packer_id')
            ->map(function ($packerLists) {
                $completed = $packerLists->where('status', PackingList::STATUS_COMPLETED);
                $totalPackages = $packerLists->sum('total_packages');
                $totalWeight = $packerLists->sum('total_weight');

                return [
                    'packer_id' => $packerLists->first()->packer_id,
                    'total_lists' => $packerLists->count(),
                    'completed_lists' => $completed->count(),
                    'total_packages' => $totalPackages,
                    'total_weight' => $totalWeight,
                    'completion_rate' => $packerLists->count() > 0 ? ($completed->count() / $packerLists->count()) * 100 : 0,
                ];
            })
            ->sortByDesc('completion_rate');
    }

    /**
     * Generate packing labels
     */
    public function generatePackingLabels(PackingList $packingList): array
    {
        $labels = [];

        foreach ($packingList->items as $item) {
            $labels[] = [
                'package_number' => $item->package_number,
                'order_number' => $packingList->salesOrder?->order_number,
                'product_name' => $item->product?->name,
                'quantity' => $item->quantity,
                'weight' => $item->weight,
                'dimensions' => $item->dimensions,
                'fragile' => $item->fragile,
                'barcode' => $this->generateBarcode($item),
            ];
        }

        return $labels;
    }

    /**
     * Generate barcode for item
     */
    protected function generateBarcode(PackingListItem $item): string
    {
        return "PK-{$item->packing_list_id}-{$item->id}";
    }

    /**
     * Validate packing before completion
     */
    public function validatePacking(PackingList $packingList): array
    {
        $errors = [];
        $warnings = [];

        // Check if all items from picking list are packed
        $pickingList = $packingList->pickingList;
        $pickedItems = $pickingList->items->where('status', PickingListItem::STATUS_PICKED)->count();
        $packedItems = $packingList->items->count();

        if ($pickedItems !== $packedItems) {
            $errors[] = "Picked items ({$pickedItems}) do not match packed items ({$packedItems})";
        }

        // Check for fragile items
        $fragileItems = $packingList->items->where('fragile', true);
        if ($fragileItems->count() > 0) {
            $warnings[] = "Contains {$fragileItems->count()} fragile items - ensure proper packaging";
        }

        // Check weight limits
        if ($packingList->total_weight > 30) {
            $warnings[] = "Package weight ({$packingList->total_weight}kg) exceeds standard limit - consider splitting";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
