<?php

namespace App\Services;

use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\LandedCost;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

class ErpUpgradeService
{
    /**
     * Allocate landed costs to items in a purchase receipt.
     */
    public function allocateLandedCost(
        int $purchaseReceiptId,
        float $shipping,
        float $customs,
        float $insurance,
        float $other,
        string $method = 'value'
    ): LandedCost {
        return DB::transaction(function () use ($purchaseReceiptId, $shipping, $customs, $insurance, $other, $method) {
            $receipt = PurchaseReceipt::findOrFail($purchaseReceiptId);
            $items = PurchaseReceiptItem::where('purchase_receipt_id', $purchaseReceiptId)->get();

            if ($items->isEmpty()) {
                throw new \Exception("لا توجد أصناف في مستند الاستلام لتخصيص التكاليف.");
            }

            // Create LandedCost record
            $landedCost = LandedCost::create([
                'purchase_receipt_id' => $purchaseReceiptId,
                'shipping_charges' => $shipping,
                'customs_duties' => $customs,
                'insurance_cost' => $insurance,
                'other_charges' => $other,
                'allocation_method' => $method,
            ]);

            $totalAdditionalCost = $shipping + $customs + $insurance + $other;

            if ($method === 'quantity') {
                $totalQuantity = $items->sum('quantity');
                if ($totalQuantity <= 0) {
                    throw new \Exception("إجمالي الكميات يجب أن يكون أكبر من الصفر.");
                }

                foreach ($items as $item) {
                    $itemCostShare = ($item->quantity / $totalQuantity) * $totalAdditionalCost;
                    $unitShare = $itemCostShare / $item->quantity;
                    $item->unit_price = $item->unit_price + $unitShare;
                    $item->total = $item->quantity * $item->unit_price;
                    $item->save();
                }
            } else { // Default to 'value'
                $totalValue = $items->sum('total');
                if ($totalValue <= 0) {
                    throw new \Exception("إجمالي قيمة الفاتورة يجب أن يكون أكبر من الصفر.");
                }

                foreach ($items as $item) {
                    $itemCostShare = ($item->total / $totalValue) * $totalAdditionalCost;
                    $unitShare = $itemCostShare / $item->quantity;
                    $item->unit_price = $item->unit_price + $unitShare;
                    $item->total = $item->quantity * $item->unit_price;
                    $item->save();
                }
            }

            return $landedCost;
        });
    }

    /**
     * Reserve inventory in a specific warehouse.
     */
    public function reserveInventory(int $warehouseId, int $productId, ?int $variantId, int $quantity): bool
    {
        return DB::transaction(function () use ($warehouseId, $productId, $variantId, $quantity) {
            $inventory = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                // Initialize inventory if not found
                $inventory = WarehouseInventory::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'reorder_point' => 10,
                    'safety_stock' => 5,
                ]);
            }

            // Check if available stock is sufficient
            $available = $inventory->quantity - $inventory->reserved_quantity;
            if ($available < $quantity) {
                return false;
            }

            $inventory->increment('reserved_quantity', $quantity);
            return true;
        });
    }

    /**
     * Release reserved inventory (either due to shipping or order cancellation).
     */
    public function releaseInventory(int $warehouseId, int $productId, ?int $variantId, int $quantity, bool $isShipped = false): bool
    {
        return DB::transaction(function () use ($warehouseId, $productId, $variantId, $quantity, $isShipped) {
            $inventory = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$inventory || $inventory->reserved_quantity < $quantity) {
                return false;
            }

            $inventory->decrement('reserved_quantity', $quantity);

            if ($isShipped) {
                // If shipped, deduct from actual stock as well
                $inventory->decrement('quantity', $quantity);
            }

            return true;
        });
    }
}
