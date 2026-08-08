<?php

namespace App\Services\Inventory;

use App\Models\ProductWarehouseAssignment;
use App\Models\WarehouseInventory;
use App\Models\InventoryTransfer;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MrpService
{
    /**
     * Run MRP calculation for a specific product across all warehouses
     */
    public function runMrpForProduct($productId, $horizonDays = 90)
    {
        $assignments = ProductWarehouseAssignment::active()
            ->forProduct($productId)
            ->where('planning_method', 'mrp')
            ->with(['warehouse', 'inventory', 'supplier'])
            ->get();

        $results = [];

        foreach ($assignments as $assignment) {
            $results[] = $this->calculateWarehouseMrp($assignment, $horizonDays);
        }

        return $results;
    }

    /**
     * Run MRP calculation for a specific warehouse
     */
    public function runMrpForWarehouse($warehouseId, $horizonDays = 90)
    {
        $assignments = ProductWarehouseAssignment::active()
            ->forWarehouse($warehouseId)
            ->where('planning_method', 'mrp')
            ->with(['product', 'inventory', 'supplier'])
            ->get();

        $results = [];

        foreach ($assignments as $assignment) {
            $results[] = $this->calculateWarehouseMrp($assignment, $horizonDays);
        }

        return $results;
    }

    /**
     * Calculate MRP for a specific product-warehouse assignment
     */
    private function calculateWarehouseMrp(ProductWarehouseAssignment $assignment, $horizonDays)
    {
        $warehouse = $assignment->warehouse;
        $product = $assignment->product;
        $inventory = $assignment->inventory->first();

        $currentStock = $inventory ? $inventory->available_quantity : 0;
        $leadTime = $assignment->lead_time_days;
        $safetyStock = $assignment->safety_stock;
        $minStock = $assignment->min_stock_level;
        $maxStock = $assignment->max_stock_level;

        // Calculate forecasted demand (based on historical sales)
        $dailyDemand = $this->calculateDailyDemand($assignment->product_id, $assignment->warehouse_id);
        
        // Calculate gross requirements (sales orders + forecasts)
        $grossRequirements = $this->calculateGrossRequirements(
            $assignment->product_id,
            $assignment->warehouse_id,
            $horizonDays,
            $dailyDemand
        );

        // Calculate scheduled receipts (open purchase orders + transfers)
        $scheduledReceipts = $this->calculateScheduledReceipts(
            $assignment->product_id,
            $assignment->warehouse_id,
            $horizonDays
        );

        // Calculate planned order releases
        $plannedOrderReleases = $this->calculatePlannedOrderReleases(
            $currentStock,
            $safetyStock,
            $grossRequirements,
            $scheduledReceipts,
            $leadTime,
            $minStock,
            $maxStock,
            $horizonDays
        );

        return [
            'assignment_id' => $assignment->id,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ],
            'warehouse' => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
            ],
            'current_stock' => $currentStock,
            'safety_stock' => $safetyStock,
            'min_stock' => $minStock,
            'max_stock' => $maxStock,
            'lead_time_days' => $leadTime,
            'daily_demand' => $dailyDemand,
            'replenishment_method' => $assignment->replenishment_method,
            'supplier' => $assignment->supplier ? [
                'id' => $assignment->supplier->id,
                'name' => $assignment->supplier->name,
            ] : null,
            'gross_requirements' => $grossRequirements,
            'scheduled_receipts' => $scheduledReceipts,
            'planned_order_releases' => $plannedOrderReleases,
            'recommendations' => $this->generateRecommendations(
                $assignment,
                $plannedOrderReleases,
                $currentStock
            ),
        ];
    }

    /**
     * Calculate daily demand based on historical sales
     */
    private function calculateDailyDemand($productId, $warehouseId)
    {
        // Get sales from the last 30 days
        $startDate = Carbon::now()->subDays(30);
        
        $totalSold = DB::table('stock_movements')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('movement_type', 'out')
            ->where('created_at', '>=', $startDate)
            ->sum('quantity');

        return $totalSold / 30; // Average daily sales
    }

    /**
     * Calculate gross requirements (sales orders + forecasted demand)
     */
    private function calculateGrossRequirements($productId, $warehouseId, $horizonDays, $dailyDemand)
    {
        $requirements = [];
        $today = Carbon::now();

        for ($day = 0; $day <= $horizonDays; $day++) {
            $date = $today->copy()->addDays($day);
            
            // Get actual sales orders for this date
            $salesOrderQuantity = DB::table('sales_orders')
                ->join('sales_order_items', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
                ->where('sales_order_items.product_id', $productId)
                ->where('sales_orders.fulfillment_warehouse_id', $warehouseId)
                ->where('sales_orders.delivery_date', $date->toDateString())
                ->where('sales_orders.status', '!=', 'cancelled')
                ->sum('sales_order_items.quantity');

            // Forecasted demand based on historical data
            $forecastedDemand = $dailyDemand;

            $requirements[$date->toDateString()] = [
                'sales_orders' => $salesOrderQuantity,
                'forecast' => $forecastedDemand,
                'total' => $salesOrderQuantity + $forecastedDemand,
            ];
        }

        return $requirements;
    }

    /**
     * Calculate scheduled receipts (open purchase orders + in-transit transfers)
     */
    private function calculateScheduledReceipts($productId, $warehouseId, $horizonDays)
    {
        $receipts = [];
        $today = Carbon::now();

        // Purchase orders
        $purchaseOrders = DB::table('purchase_orders')
            ->join('purchase_order_items', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_order_items.product_id', $productId)
            ->where('purchase_orders.warehouse_id', $warehouseId)
            ->whereIn('purchase_orders.status', ['approved', 'ordered'])
            ->where('purchase_orders.expected_date', '>=', $today->toDateString())
            ->where('purchase_orders.expected_date', '<=', $today->addDays($horizonDays)->toDateString())
            ->select([
                'purchase_orders.expected_date as date',
                'purchase_order_items.quantity as quantity',
                'purchase_orders.status as status',
                DB::raw("'purchase' as type")
            ])
            ->get();

        foreach ($purchaseOrders as $po) {
            $date = $po->date;
            if (!isset($receipts[$date])) {
                $receipts[$date] = [];
            }
            $receipts[$date][] = [
                'type' => 'purchase',
                'quantity' => $po->quantity,
                'status' => $po->status,
            ];
        }

        // In-transit transfers
        $transfers = DB::table('inventory_transfers')
            ->join('inventory_transfer_items', 'inventory_transfers.id', '=', 'inventory_transfer_items.transfer_id')
            ->where('inventory_transfer_items.product_id', $productId)
            ->where('inventory_transfers.to_warehouse_id', $warehouseId)
            ->whereIn('inventory_transfers.status', ['in_transit'])
            ->where('inventory_transfers.expected_arrival', '>=', $today->toDateString())
            ->where('inventory_transfers.expected_arrival', '<=', $today->addDays($horizonDays)->toDateString())
            ->select([
                'inventory_transfers.expected_arrival as date',
                'inventory_transfer_items.quantity_requested as quantity',
                'inventory_transfers.status as status',
                DB::raw("'transfer' as type")
            ])
            ->get();

        foreach ($transfers as $transfer) {
            $date = $transfer->date;
            if (!isset($receipts[$date])) {
                $receipts[$date] = [];
            }
            $receipts[$date][] = [
                'type' => 'transfer',
                'quantity' => $transfer->quantity,
                'status' => $transfer->status,
            ];
        }

        return $receipts;
    }

    /**
     * Calculate planned order releases using MRP logic
     */
    private function calculatePlannedOrderReleases(
        $currentStock,
        $safetyStock,
        $grossRequirements,
        $scheduledReceipts,
        $leadTime,
        $minStock,
        $maxStock,
        $horizonDays
    ) {
        $plannedOrders = [];
        $projectedAvailable = $currentStock;
        $today = Carbon::now();

        for ($day = 0; $day <= $horizonDays; $day++) {
            $date = $today->copy()->addDays($day);
            $dateStr = $date->toDateString();

            // Get gross requirement for this day
            $grossReq = $grossRequirements[$dateStr]['total'] ?? 0;

            // Get scheduled receipts for this day
            $scheduledReceipt = 0;
            if (isset($scheduledReceipts[$dateStr])) {
                foreach ($scheduledReceipts[$dateStr] as $receipt) {
                    $scheduledReceipt += $receipt['quantity'];
                }
            }

            // Calculate net requirement
            $netRequirement = max(0, $grossReq + $safetyStock - $projectedAvailable - $scheduledReceipt);

            // Calculate order quantity (use max stock as lot size if applicable)
            if ($netRequirement > 0) {
                $orderQuantity = max($netRequirement, $minStock);
                if ($maxStock > 0) {
                    $orderQuantity = min($orderQuantity, $maxStock - $projectedAvailable);
                }

                // Calculate release date (lead time before requirement date)
                $releaseDate = $date->copy()->subDays($leadTime);
                
                if ($releaseDate->gte($today)) {
                    $plannedOrders[$releaseDate->toDateString()] = [
                        'quantity' => $orderQuantity,
                        'type' => $this->determineOrderType($leadTime, $minStock),
                        'required_by' => $dateStr,
                    ];
                }

                $projectedAvailable += $orderQuantity;
            }

            // Update projected available
            $projectedAvailable = $projectedAvailable + $scheduledReceipt - $grossReq;
        }

        return $plannedOrders;
    }

    /**
     * Determine the type of order to create based on replenishment method
     */
    private function determineOrderType($leadTime, $minStock)
    {
        // This would be enhanced based on the assignment's replenishment_method
        // For now, default to purchase
        return 'purchase';
    }

    /**
     * Generate actionable recommendations based on MRP results
     */
    private function generateRecommendations($assignment, $plannedOrderReleases, $currentStock)
    {
        $recommendations = [];

        // Check if current stock is below safety stock
        if ($currentStock < $assignment->safety_stock) {
            $recommendations[] = [
                'type' => 'urgent',
                'message' => 'المخزون الحالي أقل من مخزون الأمان',
                'action' => 'create_immediate_order',
                'quantity' => $assignment->safety_stock - $currentStock,
                'priority' => 'high',
            ];
        }

        // Check if current stock is below min stock
        if ($currentStock < $assignment->min_stock_level) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'المخزون الحالي أقل من الحد الأدنى',
                'action' => 'create_order',
                'quantity' => $assignment->min_stock_level - $currentStock,
                'priority' => 'medium',
            ];
        }

        // Process planned orders
        foreach ($plannedOrderReleases as $releaseDate => $order) {
            $recommendations[] = [
                'type' => 'planned',
                'message' => "طلب مخطط لـ {$order['quantity']} وحدة",
                'action' => 'create_scheduled_order',
                'quantity' => $order['quantity'],
                'release_date' => $releaseDate,
                'required_by' => $order['required_by'],
                'order_type' => $order['type'],
                'priority' => 'low',
            ];
        }

        // Check for internal transfer opportunities
        if ($assignment->replenishment_method === 'warehouse_transfer') {
            $sourceWarehouse = $this->findSourceWarehouse($assignment);
            if ($sourceWarehouse) {
                $recommendations[] = [
                    'type' => 'transfer',
                    'message' => "نقل متاح من {$sourceWarehouse['name']}",
                    'action' => 'create_transfer',
                    'source_warehouse_id' => $sourceWarehouse['id'],
                    'source_warehouse_name' => $sourceWarehouse['name'],
                    'available_quantity' => $sourceWarehouse['available'],
                    'priority' => 'medium',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Find a source warehouse for internal transfer
     */
    private function findSourceWarehouse($assignment)
    {
        $sourceAssignments = ProductWarehouseAssignment::active()
            ->forProduct($assignment->product_id)
            ->where('warehouse_id', '!=', $assignment->warehouse_id)
            ->where('available_stock', '>', $assignment->min_stock_level)
            ->with(['warehouse'])
            ->get();

        if ($sourceAssignments->isEmpty()) {
            return null;
        }

        // Return the warehouse with most available stock
        $bestSource = $sourceAssignments->sortByDesc('available_stock')->first();

        return [
            'id' => $bestSource->warehouse_id,
            'name' => $bestSource->warehouse->name,
            'available' => $bestSource->available_stock,
        ];
    }

    /**
     * Convert MRP recommendations to actual purchase orders
     */
    public function executeMrpRecommendations($assignmentId, $recommendationIds = [])
    {
        $assignment = ProductWarehouseAssignment::with(['product', 'warehouse', 'supplier'])->findOrFail($assignmentId);
        
        $mrpResult = $this->calculateWarehouseMrp($assignment, 90);
        $recommendations = $mrpResult['recommendations'];

        $executedOrders = [];

        foreach ($recommendations as $recommendation) {
            // Filter by recommendation IDs if provided
            if (!empty($recommendationIds) && !in_array($recommendation['type'], $recommendationIds)) {
                continue;
            }

            switch ($recommendation['action']) {
                case 'create_immediate_order':
                case 'create_order':
                case 'create_scheduled_order':
                    $order = $this->createPurchaseOrder($assignment, $recommendation);
                    if ($order) {
                        $executedOrders[] = $order;
                    }
                    break;

                case 'create_transfer':
                    $transfer = $this->createInventoryTransfer($assignment, $recommendation);
                    if ($transfer) {
                        $executedOrders[] = $transfer;
                    }
                    break;
            }
        }

        return $executedOrders;
    }

    /**
     * Create a purchase order based on MRP recommendation
     */
    private function createPurchaseOrder($assignment, $recommendation)
    {
        if (!$assignment->supplier_id) {
            return null;
        }

        // This would integrate with your existing PurchaseOrder model
        // For now, return a placeholder
        return [
            'type' => 'purchase_order',
            'supplier_id' => $assignment->supplier_id,
            'warehouse_id' => $assignment->warehouse_id,
            'product_id' => $assignment->product_id,
            'quantity' => $recommendation['quantity'],
            'expected_date' => $recommendation['required_by'] ?? now()->addDays($assignment->lead_time_days),
            'status' => 'draft',
        ];
    }

    /**
     * Create an inventory transfer based on MRP recommendation
     */
    private function createInventoryTransfer($assignment, $recommendation)
    {
        // This would integrate with your existing InventoryTransfer model
        // For now, return a placeholder
        return [
            'type' => 'inventory_transfer',
            'from_warehouse_id' => $recommendation['source_warehouse_id'],
            'to_warehouse_id' => $assignment->warehouse_id,
            'product_id' => $assignment->product_id,
            'quantity' => min($recommendation['quantity'], $recommendation['available_quantity']),
            'status' => 'pending',
        ];
    }
}
