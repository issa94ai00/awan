<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductWarehouseAssignment;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

class CompositeProductService
{
    /**
     * Check if a product is composite (kitted)
     */
    public function isCompositeProduct($productId): bool
    {
        return ProductComponent::where('parent_product_id', $productId)
            ->required()
            ->exists();
    }

    /**
     * Get all components of a composite product
     */
    public function getProductComponents($productId)
    {
        return ProductComponent::with(['componentProduct'])
            ->where('parent_product_id', $productId)
            ->orderBy('is_optional', 'asc')
            ->get();
    }

    /**
     * Check if all required components are available for assembly
     */
    public function canAssembleProduct($productId, $quantity = 1, $warehouseId = null)
    {
        $components = ProductComponent::where('parent_product_id', $productId)
            ->required()
            ->get();

        if ($components->isEmpty()) {
            return [
                'can_assemble' => false,
                'reason' => 'المنتج ليس منتجاً مركباً',
            ];
        }

        $missingComponents = [];
        $availableComponents = [];

        foreach ($components as $component) {
            $requiredQuantity = $component->quantity_required * $quantity;

            if ($warehouseId) {
                // Check specific warehouse
                $assignment = ProductWarehouseAssignment::active()
                    ->forProduct($component->component_product_id)
                    ->forWarehouse($warehouseId)
                    ->first();

                $available = $assignment ? $assignment->available_stock : 0;
            } else {
                // Check all warehouses
                $assignments = ProductWarehouseAssignment::active()
                    ->forProduct($component->component_product_id)
                    ->get();
                $available = $assignments->sum('available_stock');
            }

            if ($available < $requiredQuantity) {
                $missingComponents[] = [
                    'component_id' => $component->id,
                    'component_name' => $component->componentProduct->name,
                    'required' => $requiredQuantity,
                    'available' => $available,
                    'shortage' => $requiredQuantity - $available,
                ];
            } else {
                $availableComponents[] = [
                    'component_id' => $component->id,
                    'component_name' => $component->componentProduct->name,
                    'required' => $requiredQuantity,
                    'available' => $available,
                ];
            }
        }

        return [
            'can_assemble' => empty($missingComponents),
            'available_components' => $availableComponents,
            'missing_components' => $missingComponents,
            'total_components' => $components->count(),
        ];
    }

    /**
     * Get the best warehouse to assemble a composite product
     */
    public function getBestWarehouseForAssembly($productId, $quantity = 1, $customerLatitude = null, $customerLongitude = null)
    {
        $components = ProductComponent::where('parent_product_id', $productId)
            ->required()
            ->get();

        $warehouses = DB::table('warehouses')
            ->where('is_active', true)
            ->get();

        $warehouseScores = [];

        foreach ($warehouses as $warehouse) {
            $score = 0;
            $canAssemble = true;
            $componentAvailability = [];

            foreach ($components as $component) {
                $assignment = ProductWarehouseAssignment::active()
                    ->forProduct($component->component_product_id)
                    ->forWarehouse($warehouse->id)
                    ->first();

                $available = $assignment ? $assignment->available_stock : 0;
                $required = $component->quantity_required * $quantity;

                $componentAvailability[] = [
                    'component_name' => $component->componentProduct->name,
                    'required' => $required,
                    'available' => $available,
                    'is_available' => $available >= $required,
                ];

                if ($available < $required) {
                    $canAssemble = false;
                } else {
                    $score += $available; // Higher score for more available stock
                }
            }

            // Add proximity score if customer location provided
            if ($customerLatitude && $customerLongitude && $warehouse->latitude && $warehouse->longitude) {
                $distance = $this->calculateDistance(
                    $warehouse->latitude,
                    $warehouse->longitude,
                    $customerLatitude,
                    $customerLongitude
                );
                // Closer warehouses get higher scores
                $score += max(0, 1000 - $distance);
            }

            $warehouseScores[] = [
                'warehouse_id' => $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'warehouse_code' => $warehouse->code,
                'can_assemble' => $canAssemble,
                'score' => $score,
                'component_availability' => $componentAvailability,
                'distance_km' => $distance ?? null,
            ];
        }

        // Sort by score (highest first) and filter by can_assemble
        $sortedWarehouses = collect($warehouseScores)
            ->filter(function ($w) {
                return $w['can_assemble'];
            })
            ->sortByDesc('score')
            ->values();

        return $sortedWarehouses->first();
    }

    /**
     * Create assembly order for composite product
     */
    public function createAssemblyOrder($productId, $quantity, $warehouseId, $assemblyAreaId = null)
    {
        $availability = $this->canAssembleProduct($productId, $quantity, $warehouseId);

        if (! $availability['can_assemble']) {
            return [
                'success' => false,
                'message' => 'المكونات غير متوفرة بالكامل',
                'missing_components' => $availability['missing_components'],
            ];
        }

        DB::beginTransaction();

        try {
            // Create assembly order record (you may need to create this table)
            $assemblyOrder = DB::table('product_assembly_orders')->insertGetId([
                'parent_product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'assembly_area_id' => $assemblyAreaId,
                'quantity_to_assemble' => $quantity,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Reserve components
            $components = ProductComponent::where('parent_product_id', $productId)
                ->required()
                ->get();

            foreach ($components as $component) {
                $requiredQuantity = $component->quantity_required * $quantity;

                // Create assembly order item
                DB::table('product_assembly_order_items')->insert([
                    'assembly_order_id' => $assemblyOrder,
                    'component_product_id' => $component->component_product_id,
                    'quantity_required' => $requiredQuantity,
                    'quantity_reserved' => $requiredQuantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Reserve inventory
                $inventory = WarehouseInventory::where('warehouse_id', $warehouseId)
                    ->where('product_id', $component->component_product_id)
                    ->first();

                if ($inventory) {
                    // Holding stock moves nothing between the condition buckets:
                    // the units are still sound and still on the shelf, merely
                    // spoken for. Taking them out of `available_quantity` as well
                    // removed them from availability twice — reserving 10 made 20
                    // disappear — and left `quantity` disagreeing with its own
                    // buckets for as long as the assembly order stayed open.
                    $inventory->reserved_quantity += $requiredQuantity;
                    $inventory->save();
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم إنشاء أمر التجميع بنجاح',
                'assembly_order_id' => $assemblyOrder,
                'quantity' => $quantity,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء أمر التجميع: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Complete assembly order and update inventory
     */
    public function completeAssemblyOrder($assemblyOrderId)
    {
        $assemblyOrder = DB::table('product_assembly_orders')
            ->where('id', $assemblyOrderId)
            ->where('status', 'pending')
            ->first();

        if (! $assemblyOrder) {
            return [
                'success' => false,
                'message' => 'أمر التجميع غير موجود أو تم إكماله بالفعل',
            ];
        }

        DB::beginTransaction();

        try {
            // Get assembly items
            $assemblyItems = DB::table('product_assembly_order_items')
                ->where('assembly_order_id', $assemblyOrderId)
                ->get();

            // Consume components
            foreach ($assemblyItems as $item) {
                $inventory = WarehouseInventory::where('warehouse_id', $assemblyOrder->warehouse_id)
                    ->where('product_id', $item->component_product_id)
                    ->first();

                if ($inventory) {
                    // Consuming takes the units off the shelf, out of the sound
                    // bucket and out of the hold together. `available_quantity`
                    // was left untouched here, so the buckets no longer added up
                    // to `quantity` and the row claimed sound stock it had eaten.
                    $inventory->quantity -= $item->quantity_required;
                    $inventory->available_quantity -= $item->quantity_required;
                    $inventory->reserved_quantity = max(0, (int) $inventory->reserved_quantity - (int) $item->quantity_reserved);
                    $inventory->save();
                }
            }

            // Create assembled product inventory
            $parentInventory = WarehouseInventory::firstOrCreate(
                [
                    'warehouse_id' => $assemblyOrder->warehouse_id,
                    'product_id' => $assemblyOrder->parent_product_id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'available_quantity' => 0,
                ]
            );

            $parentInventory->quantity += $assemblyOrder->quantity_to_assemble;
            $parentInventory->available_quantity += $assemblyOrder->quantity_to_assemble;
            $parentInventory->save();

            // Update assembly order status
            DB::table('product_assembly_orders')
                ->where('id', $assemblyOrderId)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم إكمال أمر التجميع بنجاح',
                'assembly_order_id' => $assemblyOrderId,
                'quantity_assembled' => $assemblyOrder->quantity_to_assemble,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إكمال أمر التجميع: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Disassemble a composite product back to components
     */
    public function disassembleProduct($productId, $quantity, $warehouseId)
    {
        $components = ProductComponent::where('parent_product_id', $productId)
            ->required()
            ->get();

        if ($components->isEmpty()) {
            return [
                'success' => false,
                'message' => 'المنتج ليس منتجاً مركباً',
            ];
        }

        // Check if we have enough composite products to disassemble
        $inventory = WarehouseInventory::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();

        // Net of reservations: composites already promised to an order cannot
        // also be taken apart.
        if (! $inventory || $inventory->available_stock < $quantity) {
            return [
                'success' => false,
                'message' => 'المخزون غير كافٍ للتفكيك',
                'available' => $inventory ? $inventory->available_stock : 0,
                'requested' => $quantity,
            ];
        }

        DB::beginTransaction();

        try {
            // Consume composite product
            $inventory->quantity -= $quantity;
            $inventory->available_quantity -= $quantity;
            $inventory->save();

            // Return components to inventory
            foreach ($components as $component) {
                $returnedQuantity = $component->quantity_required * $quantity;

                $componentInventory = WarehouseInventory::firstOrCreate(
                    [
                        'warehouse_id' => $warehouseId,
                        'product_id' => $component->component_product_id,
                    ],
                    [
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'available_quantity' => 0,
                    ]
                );

                $componentInventory->quantity += $returnedQuantity;
                $componentInventory->available_quantity += $returnedQuantity;
                $componentInventory->save();
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم تفكيك المنتج بنجاح',
                'quantity_disassembled' => $quantity,
                'components_returned' => $components->count(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تفكيك المنتج: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get bill of materials (BOM) for a composite product
     */
    public function getBillOfMaterials($productId)
    {
        $components = ProductComponent::with(['componentProduct'])
            ->where('parent_product_id', $productId)
            ->orderBy('is_optional', 'asc')
            ->get();

        $bom = [
            'parent_product_id' => $productId,
            'parent_product_name' => Product::find($productId)?->name,
            'total_components' => $components->count(),
            'required_components' => $components->where('is_optional', false)->count(),
            'optional_components' => $components->where('is_optional', true)->count(),
            'components' => $components->map(function ($component) {
                return [
                    'component_id' => $component->id,
                    'component_product_id' => $component->component_product_id,
                    'component_name' => $component->componentProduct->name,
                    'component_sku' => $component->componentProduct->sku,
                    'quantity_required' => $component->quantity_required,
                    'is_optional' => $component->is_optional,
                    'notes' => $component->notes,
                ];
            }),
        ];

        return $bom;
    }

    /**
     * Calculate cost of composite product based on component costs
     */
    public function calculateCompositeProductCost($productId)
    {
        $components = ProductComponent::with(['componentProduct'])
            ->where('parent_product_id', $productId)
            ->required()
            ->get();

        $totalCost = 0;
        $componentCosts = [];

        foreach ($components as $component) {
            $componentCost = $component->componentProduct->cost_price ?? 0;
            $lineCost = $componentCost * $component->quantity_required;
            $totalCost += $lineCost;

            $componentCosts[] = [
                'component_name' => $component->componentProduct->name,
                'quantity' => $component->quantity_required,
                'unit_cost' => $componentCost,
                'line_cost' => $lineCost,
            ];
        }

        return [
            'parent_product_id' => $productId,
            'total_cost' => $totalCost,
            'component_costs' => $componentCosts,
        ];
    }

    /**
     * Calculate distance between two coordinates
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        if (! $lat1 || ! $lon1 || ! $lat2 || ! $lon2) {
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
     * Update component quantities for a composite product
     */
    public function updateComponentQuantities($productId, $componentUpdates)
    {
        DB::beginTransaction();

        try {
            foreach ($componentUpdates as $update) {
                ProductComponent::where('id', $update['component_id'])
                    ->where('parent_product_id', $productId)
                    ->update([
                        'quantity_required' => $update['quantity'],
                        'is_optional' => $update['is_optional'] ?? false,
                        'notes' => $update['notes'] ?? null,
                    ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم تحديث المكونات بنجاح',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث المكونات: '.$e->getMessage(),
            ];
        }
    }
}
