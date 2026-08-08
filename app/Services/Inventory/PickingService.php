<?php

namespace App\Services\Inventory;

use App\Models\ProductWarehouseAssignment;
use App\Models\WarehouseInventory;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\Product;
use App\Models\ProductComponent;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PickingService
{
    /**
     * Get the best warehouse for picking based on geographic proximity to customer
     */
    public function getBestWarehouseForPicking($productId, $customerLatitude, $customerLongitude, $quantity = 1)
    {
        $assignments = ProductWarehouseAssignment::active()
            ->forProduct($productId)
            ->with(['warehouse', 'inventory'])
            ->get()
            ->filter(function ($assignment) use ($quantity) {
                return $assignment->available_stock >= $quantity;
            });

        if ($assignments->isEmpty()) {
            return null;
        }

        // Calculate distance for each warehouse and sort by proximity
        $sortedAssignments = $assignments->map(function ($assignment) use ($customerLatitude, $customerLongitude) {
            $distance = $this->calculateDistance(
                $assignment->warehouse->latitude,
                $assignment->warehouse->longitude,
                $customerLatitude,
                $customerLongitude
            );

            return [
                'assignment' => $assignment,
                'distance_km' => $distance,
            ];
        })->sortBy('distance_km');

        return $sortedAssignments->first()['assignment'];
    }

    /**
     * Generate picking plan for a sales order with Directed FIFO logic
     */
    public function generatePickingPlan($salesOrderId, $customerLatitude = null, $customerLongitude = null)
    {
        // Get sales order items
        $orderItems = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->where('sales_order_items.sales_order_id', $salesOrderId)
            ->where('sales_orders.status', '!=', 'cancelled')
            ->select([
                'sales_order_items.id as item_id',
                'sales_order_items.product_id',
                'sales_order_items.product_variant_id',
                'sales_order_items.quantity as ordered_quantity',
                'sales_orders.customer_id',
                'sales_orders.shipping_address_latitude',
                'sales_orders.shipping_address_longitude',
            ])
            ->get();

        if ($orderItems->isEmpty()) {
            return null;
        }

        $pickingPlan = [];
        $warehouseGroups = [];

        foreach ($orderItems as $item) {
            // Use shipping address if customer coordinates not provided
            $custLat = $customerLatitude ?? $item->shipping_address_latitude;
            $custLng = $customerLongitude ?? $item->shipping_address_longitude;

            // Check if product is composite
            $product = Product::find($item->product_id);
            
            if ($product && $product->isComposite()) {
                // Handle composite product - pick components instead
                $componentPickingPlan = $this->generateCompositeProductPickingPlan(
                    $item->product_id,
                    $item->ordered_quantity,
                    $custLat,
                    $custLng
                );
                
                foreach ($componentPickingPlan as $componentPlan) {
                    $warehouseId = $componentPlan['warehouse_id'];
                    if (!isset($warehouseGroups[$warehouseId])) {
                        $warehouseGroups[$warehouseId] = [];
                    }
                    $warehouseGroups[$warehouseId][] = $componentPlan;
                }
            } else {
                // Handle regular product
                $bestAssignment = $this->getBestWarehouseForPicking(
                    $item->product_id,
                    $custLat,
                    $custLng,
                    $item->ordered_quantity
                );

                if ($bestAssignment) {
                    $warehouseId = $bestAssignment->warehouse_id;
                    
                    // Get best bins for picking (FIFO logic)
                    $bins = $this->getBestBinsForPicking(
                        $bestAssignment,
                        $item->ordered_quantity
                    );

                    if (!isset($warehouseGroups[$warehouseId])) {
                        $warehouseGroups[$warehouseId] = [];
                    }

                    $warehouseGroups[$warehouseId][] = [
                        'item_id' => $item->item_id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'product_name' => $product ? $product->name : 'Unknown',
                        'ordered_quantity' => $item->ordered_quantity,
                        'warehouse_id' => $warehouseId,
                        'warehouse_name' => $bestAssignment->warehouse->name,
                        'warehouse_code' => $bestAssignment->warehouse->code,
                        'bins' => $bins,
                        'is_composite' => false,
                    ];
                }
            }
        }

        // Create picking lists for each warehouse
        foreach ($warehouseGroups as $warehouseId => $items) {
            $pickingPlan[] = $this->createPickingListForWarehouse(
                $salesOrderId,
                $warehouseId,
                $items
            );
        }

        return $pickingPlan;
    }

    /**
     * Get best bins for picking using FIFO logic
     */
    private function getBestBinsForPicking($assignment, $quantity)
    {
        $inventoryRecords = WarehouseInventory::where('warehouse_id', $assignment->warehouse_id)
            ->where('product_id', $assignment->product_id)
            ->where('available_quantity', '>', 0)
            ->with(['bin'])
            ->orderBy('created_at', 'asc') // FIFO: oldest first
            ->get();

        $bins = [];
        $remainingQuantity = $quantity;

        foreach ($inventoryRecords as $inventory) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $pickQuantity = min($inventory->available_quantity, $remainingQuantity);
            
            $bins[] = [
                'bin_id' => $inventory->bin_id,
                'bin_code' => $inventory->bin ? $inventory->bin->bin_code : 'Unassigned',
                'bin_location' => $inventory->bin ? $inventory->bin->location_code : null,
                'quantity_to_pick' => $pickQuantity,
                'batch_number' => $inventory->batch_number,
                'expiry_date' => $inventory->expiry_date,
                'cost_basis' => $inventory->cost_basis,
            ];

            $remainingQuantity -= $pickQuantity;
        }

        return $bins;
    }

    /**
     * Generate picking plan for composite/kitted products
     */
    private function generateCompositeProductPickingPlan($productId, $quantity, $customerLatitude, $customerLongitude)
    {
        $components = ProductComponent::where('parent_product_id', $productId)
            ->required()
            ->with(['componentProduct'])
            ->get();

        $pickingPlan = [];

        foreach ($components as $component) {
            $requiredQuantity = $component->quantity_required * $quantity;
            
            // Get best warehouse for this component
            $bestAssignment = $this->getBestWarehouseForPicking(
                $component->component_product_id,
                $customerLatitude,
                $customerLongitude,
                $requiredQuantity
            );

            if ($bestAssignment) {
                $bins = $this->getBestBinsForPicking(
                    $bestAssignment,
                    $requiredQuantity
                );

                $pickingPlan[] = [
                    'item_id' => null,
                    'product_id' => $component->component_product_id,
                    'product_variant_id' => null,
                    'product_name' => $component->componentProduct->name,
                    'ordered_quantity' => $requiredQuantity,
                    'warehouse_id' => $bestAssignment->warehouse_id,
                    'warehouse_name' => $bestAssignment->warehouse->name,
                    'warehouse_code' => $bestAssignment->warehouse->code,
                    'bins' => $bins,
                    'is_composite' => true,
                    'parent_product_id' => $productId,
                    'component_id' => $component->id,
                ];
            }
        }

        return $pickingPlan;
    }

    /**
     * Create picking list for a specific warehouse
     */
    private function createPickingListForWarehouse($salesOrderId, $warehouseId, $items)
    {
        // Create picking list record
        $pickingList = PickingList::create([
            'sales_order_id' => $salesOrderId,
            'warehouse_id' => $warehouseId,
            'status' => 'pending',
            'assigned_to' => null,
            'priority' => $this->calculatePickingPriority($items),
            'estimated_pick_time' => $this->estimatePickTime($items),
            'notes' => null,
        ]);

        // Create picking list items
        foreach ($items as $item) {
            foreach ($item['bins'] as $bin) {
                PickingListItem::create([
                    'picking_list_id' => $pickingList->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'bin_id' => $bin['bin_id'],
                    'quantity_to_pick' => $bin['quantity_to_pick'],
                    'quantity_picked' => 0,
                    'batch_number' => $bin['batch_number'],
                    'expiry_date' => $bin['expiry_date'],
                    'status' => 'pending',
                    'is_composite_component' => $item['is_composite'],
                    'parent_product_id' => $item['is_composite'] ? $item['parent_product_id'] : null,
                ]);
            }
        }

        return [
            'picking_list	id' => $pickingList->id,
            'warehouse_id' => $warehouseId,
            'warehouse_name' => $items[0]['warehouse_name'],
            'status' => $pickingList->status,
            'priority' => $pickingList->priority,
            'estimated_pick_time' => $pickingList->estimated_pick_time,
            'items_count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * Calculate picking priority based on various factors
     */
    private function calculatePickingPriority($items)
    {
        $priority = 'normal';

        // Check if any item is urgent (e.g., same-day delivery)
        foreach ($items as $item) {
            if ($item['is_composite']) {
                $priority = 'high'; // Composite products need more time
                break;
            }
        }

        return $priority;
    }

    /**
     * Estimate pick time based on number of items and bins
     */
    private function estimatePickTime($items)
    {
        $totalBins = 0;
        foreach ($items as $item) {
            $totalBins += count($item['bins']);
        }

        // Assume 2 minutes per bin
        return $totalBins * 2;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return PHP_FLOAT_MAX;
        }

        $earthRadius = 6371; // km
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Optimize picking route within a warehouse (Traveling Salesman Problem approximation)
     */
    public function optimizePickingRoute($pickingListId)
    {
        $pickingList = PickingList::with(['items.bin', 'warehouse'])->findOrFail($pickingListId);
        
        if ($pickingList->items->isEmpty()) {
            return null;
        }

        // Get all unique bins with their coordinates
        $bins = $pickingList->items
            ->map(function ($item) {
                return [
                    'id' => $item->bin_id,
                    'code' => $item->bin ? $item->bin->bin_code : null,
                    'coordinates' => $item->bin ? $item->bin->coordinates : null,
                    'zone' => $item->bin ? $item->bin->zone : null,
                    'aisle' => $item->bin ? $item->bin->aisle : null,
                    'items' => $pickingList->items->filter(function ($i) use ($item) {
                        return $i->bin_id === $item->bin_id;
                    })->values(),
                ];
            })
            ->unique('id')
            ->values();

        // Simple route optimization: sort by zone, then aisle, then shelf
        $optimizedBins = $bins->sortBy([
            ['zone', 'asc'],
            ['aisle', 'asc'],
            ['code', 'asc'],
        ])->values();

        return [
            'picking_list_id' => $pickingListId,
            'optimized_route' => $optimizedBins,
            'total_stops' => $optimizedBins->count(),
            'estimated_distance' => $this->estimateRouteDistance($optimizedBins),
        ];
    }

    /**
     * Estimate total route distance
     */
    private function estimateRouteDistance($bins)
    {
        if ($bins->count() < 2) {
            return 0;
        }

        $totalDistance = 0;
        for ($i = 0; $i < $bins->count() - 1; $i++) {
            $current = $bins[$i];
            $next = $bins[$i + 1];

            if ($current['coordinates'] && $next['coordinates']) {
                $distance = $this->calculateDistance(
                    $current['coordinates']['lat'] ?? 0,
                    $current['coordinates']['lng'] ?? 0,
                    $next['coordinates']['lat'] ?? 0,
                    $next['coordinates']['lng'] ?? 0
                );
                $totalDistance += $distance;
            }
        }

        return $totalDistance;
    }

    /**
     * Confirm picking completion and update inventory
     */
    public function confirmPicking($pickingListId, $pickerId)
    {
        $pickingList = PickingList::with(['items'])->findOrFail($pickingListId);

        DB::beginTransaction();

        try {
            foreach ($pickingList->items as $item) {
                // Update picking item
                $item->update([
                    'quantity_picked' => $item->quantity_to_pick,
                    'picked_by' => $pickerId,
                    'picked_at' => now(),
                    'status' => 'completed',
                ]);

                // Update inventory
                $inventory = WarehouseInventory::where('warehouse_id', $pickingList->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->where('bin_id', $item->bin_id)
                    ->first();

                if ($inventory) {
                    $inventory->quantity -= $item->quantity_to_pick;
                    $inventory->available_quantity -= $item->quantity_to_pick;
                    $inventory->reserved_quantity -= $item->quantity_to_pick;
                    $inventory->save();
                }
            }

            // Update picking list
            $pickingList->update([
                'status' => 'completed',
                'completed_at' => now(),
                'assigned_to' => $pickerId,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم تأكيد الانتقاء بنجاح',
                'picking_list_id' => $pickingListId,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تأكيد الانتقاء: ' . $e->getMessage(),
            ];
        }
    }
}
