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
     * Adds freight, customs and insurance to what the goods actually cost.
     *
     * Landed cost is part of the value of stock: the business paid it to get
     * the goods onto its own shelf, and a margin computed without it overstates
     * every sale of those units.
     *
     * The previous version allocated the charge across the receipt's item rows
     * and stopped there, rewriting `unit_price` on each one. Three records were
     * left disagreeing, and none of them said so:
     *
     *  - the **receipt** now showed totals that no longer matched the journal
     *    entry posted from it when the goods arrived;
     *  - the **cost layers** — which is what a sale is actually costed against —
     *    still held the original price, so the freight never reached a single
     *    cost of sale;
     *  - the **ledger** never heard of the charge at all: inventory did not
     *    gain it, no expense recorded it, and nobody was recorded as owed it.
     *
     * What happens now instead. The item rows keep the price the supplier
     * charged, because that is what the supplier charged and what the receipt's
     * own entry was posted from. The charge is applied to the cost layers the
     * receipt opened, and split by where those units are today:
     *
     *   Dr  Inventory              the share still on the shelf
     *   Dr  Cost of goods sold     the share already sold
     *       Cr  Accounts payable / Cash    the whole charge
     *
     * The sold share belongs in cost of sales because those units left at a
     * cost that is now known to have been too low, and the period that sold
     * them is the period that should carry the difference.
     *
     * @param  string  $settlement  `credit` (owed to a carrier, which must then
     *                              be named), `cash`, or `bank`
     * @param  ?int  $supplierId  who the charge is owed to, required on credit
     *                            so the payables subsidiary keeps matching its
     *                            control account
     */
    public function allocateLandedCost(
        int $purchaseReceiptId,
        float $shipping,
        float $customs,
        float $insurance,
        float $other,
        string $method = 'value',
        string $settlement = 'credit',
        ?int $supplierId = null
    ): LandedCost {
        $total = round($shipping + $customs + $insurance + $other, 2);

        if ($total <= 0) {
            throw new \RuntimeException('لا توجد تكاليف إضافية لتوزيعها.');
        }

        if ($settlement === 'credit' && ! $supplierId) {
            throw new \RuntimeException('التكاليف على الحساب تحتاج مورّداً تُقيَّد عليه، وإلا اختلّت مطابقة ذمم الموردين.');
        }

        return DB::transaction(function () use (
            $purchaseReceiptId, $shipping, $customs, $insurance, $other, $method, $settlement, $supplierId, $total
        ) {
            $receipt = PurchaseReceipt::findOrFail($purchaseReceiptId);
            $items = PurchaseReceiptItem::where('purchase_receipt_id', $purchaseReceiptId)->get();

            if ($items->isEmpty()) {
                throw new \RuntimeException('لا توجد أصناف في مستند الاستلام لتخصيص التكاليف.');
            }

            $basis = $method === 'quantity'
                ? (float) $items->sum('quantity')
                : (float) $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);

            if ($basis <= 0) {
                throw new \RuntimeException('أساس التوزيع صفر — راجع كميات الإيصال وأسعاره.');
            }

            $landedCost = LandedCost::create([
                'purchase_receipt_id' => $purchaseReceiptId,
                'supplier_id' => $settlement === 'credit' ? $supplierId : null,
                'shipping_charges' => $shipping,
                'customs_duties' => $customs,
                'insurance_cost' => $insurance,
                'other_charges' => $other,
                'allocation_method' => $method,
                'settlement' => $settlement,
            ]);

            $onShelf = 0.0;
            $alreadySold = 0.0;

            foreach ($items as $item) {
                $quantity = (int) $item->quantity;

                if ($quantity <= 0) {
                    continue;
                }

                $weight = $method === 'quantity'
                    ? $quantity
                    : $quantity * (float) $item->unit_price;

                $share = $total * ($weight / $basis);
                $perUnit = $share / $quantity;

                // The layers this receipt opened, which is where the cost of a
                // future sale is read from.
                $layers = DB::table('inventory_cost_layers')
                    ->where('product_id', $item->product_id)
                    ->where('source', 'purchase_receipt')
                    ->where('reference', $receipt->receipt_number)
                    ->get();

                foreach ($layers as $layer) {
                    DB::table('inventory_cost_layers')->where('id', $layer->id)->update([
                        'unit_cost' => round((float) $layer->unit_cost + $perUnit, 4),
                        'updated_at' => now(),
                    ]);

                    $remaining = (int) $layer->remaining_quantity;
                    $consumed = max(0, (int) $layer->received_quantity - $remaining);

                    $onShelf += $perUnit * $remaining;
                    $alreadySold += $perUnit * $consumed;
                }
            }

            $this->postLandedCost($landedCost, $receipt, round($onShelf, 2), round($alreadySold, 2), $settlement, $supplierId);

            return $landedCost;
        });
    }

    /**
     * The ledger side of a landed cost.
     *
     * Rounding is settled against the total rather than left to the sum of the
     * parts: the two debits are shares of one charge, and a cent lost between
     * them would make the entry refuse to balance.
     */
    private function postLandedCost(
        LandedCost $landedCost,
        PurchaseReceipt $receipt,
        float $onShelf,
        float $alreadySold,
        string $settlement,
        ?int $supplierId
    ): void {
        $total = round(
            (float) $landedCost->shipping_charges + (float) $landedCost->customs_duties
            + (float) $landedCost->insurance_cost + (float) $landedCost->other_charges,
            2
        );

        $ledger = app(\App\Services\Accounting\LedgerPostingService::class);
        $label = 'تكاليف إضافية - '.($receipt->receipt_number ?? ('#'.$receipt->id));

        // Whatever the split did not account for stays with the inventory side,
        // which is where the bulk of a landed cost belongs.
        $alreadySold = min($alreadySold, $total);
        $onShelf = round($total - $alreadySold, 2);

        $lines = [];

        if ($onShelf > 0) {
            $warehouseId = (int) ($receipt->warehouse_id ?? 0);

            $lines[] = ($warehouseId
                ? ['account_id' => $ledger->inventoryAccountIdFor($warehouseId)]
                : ['role' => 'inventory'])
                + ['debit' => $onShelf, 'description' => 'تحميل تكاليف على المخزون - '.$label];
        }

        if ($alreadySold > 0) {
            $lines[] = ['role' => 'cogs', 'debit' => $alreadySold,
                        'description' => 'تكاليف على بضاعة بيعت - '.$label];
        }

        $creditRole = match ($settlement) {
            'cash' => 'cash',
            'bank', 'bank_transfer' => 'bank',
            default => 'accounts_payable',
        };

        $lines[] = ['role' => $creditRole, 'credit' => $total,
                    'description' => 'مستحق تكاليف إضافية - '.$label];

        $ledger->post(
            key: 'landed_cost:'.$landedCost->id,
            date: $receipt->receipt_date ? (string) $receipt->receipt_date->toDateString() : now()->toDateString(),
            description: 'إثبات '.$label,
            lines: $lines,
            reference: $landedCost,
            module: 'purchases',
        );

        // On credit the charge is owed to somebody, and the payables subsidiary
        // has to move with the control account or the aging report stops
        // reconciling.
        if ($creditRole === 'accounts_payable' && $supplierId) {
            \App\Models\Supplier::find($supplierId)?->updateBalance($total);
        }
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
