<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductSerialNumber;
use App\Models\WarehouseInventory;
use Illuminate\Support\Collection;

class InventoryAllocationService
{
    /**
     * Allocate inventory using FIFO (First In First Out)
     */
    public function allocateFIFO(int $productId, int $quantity, int $warehouseId, ?int $variantId = null): Collection
    {
        $allocations = collect();
        $remainingQuantity = $quantity;

        // Get available batches ordered by creation date (oldest first)
        $batches = ProductBatch::byProduct($productId)
            ->byWarehouse($warehouseId)
            ->available()
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $availableQuantity = $batch->available_quantity;
            $allocateQuantity = min($availableQuantity, $remainingQuantity);

            if ($allocateQuantity > 0) {
                $allocations->push([
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $allocateQuantity,
                    'unit_cost' => $batch->unit_cost,
                    'expiry_date' => $batch->expiry_date,
                ]);

                $batch->reserve($allocateQuantity);
                $remainingQuantity -= $allocateQuantity;
            }
        }

        return $allocations;
    }

    /**
     * Allocate inventory using FEFO (First Expired First Out)
     */
    public function allocateFEFO(int $productId, int $quantity, int $warehouseId, ?int $variantId = null): Collection
    {
        $allocations = collect();
        $remainingQuantity = $quantity;

        // Get available batches ordered by expiry date (expiring first)
        $batches = ProductBatch::byProduct($productId)
            ->byWarehouse($warehouseId)
            ->available()
            ->orderBy('expiry_date', 'asc')
            ->get();

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $availableQuantity = $batch->available_quantity;
            $allocateQuantity = min($availableQuantity, $remainingQuantity);

            if ($allocateQuantity > 0) {
                $allocations->push([
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $allocateQuantity,
                    'unit_cost' => $batch->unit_cost,
                    'expiry_date' => $batch->expiry_date,
                ]);

                $batch->reserve($allocateQuantity);
                $remainingQuantity -= $allocateQuantity;
            }
        }

        return $allocations;
    }

    /**
     * Allocate inventory using LIFO (Last In First Out)
     */
    public function allocateLIFO(int $productId, int $quantity, int $warehouseId, ?int $variantId = null): Collection
    {
        $allocations = collect();
        $remainingQuantity = $quantity;

        // Get available batches ordered by creation date (newest first)
        $batches = ProductBatch::byProduct($productId)
            ->byWarehouse($warehouseId)
            ->available()
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $availableQuantity = $batch->available_quantity;
            $allocateQuantity = min($availableQuantity, $remainingQuantity);

            if ($allocateQuantity > 0) {
                $allocations->push([
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $allocateQuantity,
                    'unit_cost' => $batch->unit_cost,
                    'expiry_date' => $batch->expiry_date,
                ]);

                $batch->reserve($allocateQuantity);
                $remainingQuantity -= $allocateQuantity;
            }
        }

        return $allocations;
    }

    /**
     * Allocate inventory based on warehouse inventory cost basis setting
     */
    public function allocate(int $productId, int $quantity, int $warehouseId, ?int $variantId = null): Collection
    {
        $inventory = WarehouseInventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when($variantId, fn($q) => $q->where('product_variant_id', $variantId))
            ->first();

        $costBasis = $inventory->cost_basis ?? WarehouseInventory::COST_BASIS_FIFO;

        return match($costBasis) {
            WarehouseInventory::COST_BASIS_FIFO => $this->allocateFIFO($productId, $quantity, $warehouseId, $variantId),
            WarehouseInventory::COST_BASIS_FEFO => $this->allocateFEFO($productId, $quantity, $warehouseId, $variantId),
            WarehouseInventory::COST_BASIS_LIFO => $this->allocateLIFO($productId, $quantity, $warehouseId, $variantId),
            default => $this->allocateFIFO($productId, $quantity, $warehouseId, $variantId),
        };
    }

    /**
     * Allocate specific serial numbers
     */
    public function allocateSerialNumbers(array $serialNumbers, int $productId, int $warehouseId, ?int $variantId = null): Collection
    {
        $allocations = collect();

        foreach ($serialNumbers as $serialNumber) {
            $serial = ProductSerialNumber::bySerialNumber($serialNumber)
                ->byProduct($productId)
                ->byWarehouse($warehouseId)
                ->inStock()
                ->first();

            if ($serial) {
                $serial->reserve();
                $allocations->push([
                    'serial_id' => $serial->id,
                    'serial_number' => $serial->serial_number,
                    'batch_id' => $serial->batch_id,
                ]);
            }
        }

        return $allocations;
    }

    /**
     * Release allocated inventory
     */
    public function releaseAllocation(array $allocations): void
    {
        foreach ($allocations as $allocation) {
            if (isset($allocation['batch_id'])) {
                $batch = ProductBatch::find($allocation['batch_id']);
                if ($batch) {
                    $batch->release($allocation['quantity']);
                }
            }

            if (isset($allocation['serial_id'])) {
                $serial = ProductSerialNumber::find($allocation['serial_id']);
                if ($serial) {
                    $serial->releaseReservation();
                }
            }
        }
    }

    /**
     * Check if sufficient inventory is available
     */
    public function checkAvailability(int $productId, int $quantity, int $warehouseId, ?int $variantId = null): bool
    {
        $inventory = WarehouseInventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when($variantId, fn($q) => $q->where('product_variant_id', $variantId))
            ->first();

        if (!$inventory) {
            return false;
        }

        return $inventory->available_stock >= $quantity;
    }

    /**
     * Get total available quantity across all warehouses
     */
    public function getTotalAvailableQuantity(int $productId, ?int $variantId = null): int
    {
        return WarehouseInventory::where('product_id', $productId)
            ->when($variantId, fn($q) => $q->where('product_variant_id', $variantId))
            ->get()
            ->sum(fn($inv) => $inv->available_stock);
    }

    /**
     * Get inventory summary by warehouse
     */
    public function getInventorySummary(int $productId, ?int $variantId = null): Collection
    {
        return WarehouseInventory::where('product_id', $productId)
            ->when($variantId, fn($q) => $q->where('product_variant_id', $variantId))
            ->with(['warehouse', 'batches'])
            ->get()
            ->map(function ($inventory) {
                return [
                    'warehouse_id' => $inventory->warehouse_id,
                    'warehouse_name' => $inventory->warehouse->name,
                    'warehouse_type' => $inventory->warehouse->location_type,
                    'total_quantity' => $inventory->quantity,
                    'available_quantity' => $inventory->available_stock,
                    'reserved_quantity' => $inventory->reserved_quantity,
                    'damaged_quantity' => $inventory->damaged_quantity,
                    'quarantined_quantity' => $inventory->quarantined_quantity,
                    'reorder_point' => $inventory->reorder_point,
                    'is_below_reorder' => $inventory->isBelowReorderPoint(),
                    'batches' => $inventory->batches->map(function ($batch) {
                        return [
                            'batch_number' => $batch->batch_number,
                            'quantity' => $batch->quantity,
                            'available_quantity' => $batch->available_quantity,
                            'expiry_date' => $batch->expiry_date,
                            'is_expired' => $batch->isExpired(),
                            'is_expiring_soon' => $batch->isExpiringSoon(),
                        ];
                    }),
                ];
            });
    }
}
