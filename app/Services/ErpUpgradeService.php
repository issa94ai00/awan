<?php

namespace App\Services;

use App\Models\LandedCost;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;
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
                throw new \Exception('لا توجد أصناف في مستند الاستلام لتخصيص التكاليف.');
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
                    throw new \Exception('إجمالي الكميات يجب أن يكون أكبر من الصفر.');
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
                    throw new \Exception('إجمالي قيمة الفاتورة يجب أن يكون أكبر من الصفر.');
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
     *
     * Delegated to InventoryService so the reservation uses the same sellable
     * gate and row handling as the rest of the WMS. Rows it opens get the full
     * bucket defaults (available/damaged/quarantined), unlike the partial rows
     * this method used to create, which left every other InventoryService call
     * reading NULLs as zero.
     */
    public function reserveInventory(int $warehouseId, int $productId, ?int $variantId, int $quantity): bool
    {
        return app(InventoryService::class)->reserve($productId, $quantity, $warehouseId, $variantId);
    }

    /**
     * Release reserved inventory (either due to shipping or order cancellation).
     *
     * A plain release just hands the units back to the sellable pool. A shipped
     * release consumes the reservation as an actual issue through InventoryService,
     * which records the movement, draws down the FIFO cost layers, keeps the
     * condition buckets in balance and updates the product's cached stock count.
     * This used to decrement `quantity` directly — the movement trail, the cost
     * layers and `products.stock_quantity` were all left untouched, and the
     * bucket invariant (quantity = available + damaged + quarantined) broke the
     * moment anything had been reserved.
     */
    public function releaseInventory(int $warehouseId, int $productId, ?int $variantId, int $quantity, bool $isShipped = false): bool
    {
        $inventory = app(InventoryService::class);

        if ($isShipped) {
            // Only units actually held as reserved may ship out of the
            // reservation. Checked here, under lock, before any stock moves.
            $row = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->when($variantId !== null, fn ($q) => $q->where('product_variant_id', $variantId))
                ->lockForUpdate()
                ->first();

            if (! $row || (int) $row->reserved_quantity < $quantity) {
                return false;
            }

            $inventory->shipReserved($productId, $quantity, $warehouseId, [
                'source' => 'erp',
                'reason' => 'إخراج مخزون محجوز عبر التكامل الخارجي',
            ], $variantId);

            return true;
        }

        $inventory->release($productId, $quantity, $warehouseId, $variantId);

        return true;
    }
}
