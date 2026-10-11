<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sending goods back to the supplier, in one movement.
 *
 * Four records have to change together, and any one of them alone leaves a
 * different lie behind: the stock leaves the shelf, its cost leaves the
 * inventory account, the supplier is owed less, and the tax paid on the
 * returned portion is no longer reclaimable.
 *
 * The cost is taken from the FIFO layers as the units are consumed, exactly as
 * a sale is costed — what leaves the books is what those units really cost,
 * not what the supplier happens to be crediting today.
 */
class PurchaseReturnService
{
    public function __construct(
        private InventoryService $inventory,
        private LedgerPostingService $ledger,
    ) {
    }

    /**
     * A line may name which size of the product went back (`product_variant_id`);
     * stock is counted per product, and the size's own count follows the goods.
     *
     * @param  array<int,array{product_id:int,product_variant_id?:int|null,quantity:int,unit_price?:float}>  $items
     * @param  array{supplier_id?:int,purchase_receipt_id?:int,warehouse_id?:int,return_date?:string,reason?:string,tax_amount?:float,credit_amount?:float,notes?:string}  $attributes
     */
    public function record(array $items, array $attributes = []): PurchaseReturn
    {
        $items = array_values(array_filter($items, fn ($item) => (int) ($item['quantity'] ?? 0) > 0));
        $items = $this->mergeRepeatedProducts($items);

        if ($items === []) {
            throw new RuntimeException('لا توجد أصناف للإرجاع.');
        }

        return DB::transaction(function () use ($items, $attributes) {
            $warehouseId = (int) ($attributes['warehouse_id'] ?? 0) ?: $this->inventory->defaultWarehouseId();

            if (! $warehouseId) {
                throw new RuntimeException('لا يوجد مستودع يُخرَج منه المرتجع.');
            }

            $return = PurchaseReturn::create([
                // Derived from the last id: counting hands out a number that is
                // already taken as soon as any return is deleted.
                'return_number' => 'PRT-'.str_pad(
                    (string) (((int) PurchaseReturn::withTrashed()->max('id')) + 1), 6, '0', STR_PAD_LEFT
                ),
                'supplier_id' => $attributes['supplier_id'] ?? null,
                'purchase_receipt_id' => $attributes['purchase_receipt_id'] ?? null,
                'warehouse_id' => $warehouseId,
                'return_date' => $attributes['return_date'] ?? now()->toDateString(),
                'reason' => $attributes['reason'] ?? null,
                'tax_amount' => round((float) ($attributes['tax_amount'] ?? 0), 5),
                'credit_amount' => 0,
                'notes' => $attributes['notes'] ?? null,
                'status' => 'completed',
                'created_by' => auth()->id(),
            ]);

            $cost = 0.0;
            $credit = 0.0;

            foreach ($items as $line) {
                $quantity = (int) $line['quantity'];
                $variantId = (int) ($line['product_variant_id'] ?? 0) ?: null;

                // Refuses to take out more than is there: a return of stock the
                // warehouse does not hold is a counting error, not a return.
                // Two sizes of one product are two movements.
                $movement = $this->inventory->issue(
                    (int) $line['product_id'],
                    $quantity,
                    $warehouseId,
                    [
                        'key' => 'purchase_return:'.$return->id.':item:'.$line['product_id'].($variantId ? '-'.$variantId : ''),
                        'source' => 'purchase_return',
                        'reference' => $return->return_number,
                        'reason' => 'إرجاع إلى المورّد',
                    ]
                );

                // What those units actually cost, drawn from the layers as they
                // were consumed.
                $lineCost = $movement ? (float) $movement->total_cost : 0.0;
                $unitCost = $quantity > 0 ? round($lineCost / $quantity, 5) : 0.0;

                // What the supplier credits: their price when given, otherwise
                // what we paid, which is the honest default.
                $unitPrice = round((float) ($line['unit_price'] ?? $unitCost), 5);

                $return->items()->create([
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                ]);

                // The size's own count follows the goods out, as receiving
                // brought it in.
                ProductVariant::adjustStockCount($variantId, -$quantity);

                $cost += $lineCost;
                $credit += $unitPrice * $quantity;
            }

            // An explicit figure overrides the sum of the lines: a supplier may
            // credit one agreed amount for the whole return.
            $credit = isset($attributes['credit_amount'])
                ? round((float) $attributes['credit_amount'], 5)
                : round($credit, 5);

            $return->update(['credit_amount' => $credit]);

            $this->ledger->postPurchaseReturn($return->load('items'), round($cost, 5));

            // The supplier is owed less, by what they are crediting including
            // the tax they will no longer be paid.
            if ($return->supplier_id) {
                Supplier::find($return->supplier_id)
                    ?->updateBalance(-round($credit + (float) $return->tax_amount, 5));
            }

            return $return->load(['items', 'supplier']);
        });
    }

    /**
     * One line per product, or per size of it. Each line's stock movement is
     * keyed by the return and the product (and size), so a second line for
     * the same one got the first line's movement back: its units never left
     * the shelf, yet it was credited and costed all the same. Two sizes of one
     * product are two lines, as they should be.
     *
     * @param  array<int,array{product_id:int,product_variant_id?:int|null,quantity:int,unit_price?:float|null}>  $items
     * @return array<int,array{product_id:int,product_variant_id:int|null,quantity:int,unit_price?:float}>
     */
    private function mergeRepeatedProducts(array $items): array
    {
        $merged = [];

        foreach ($items as $line) {
            $productId = (int) $line['product_id'];
            $variantId = (int) ($line['product_variant_id'] ?? 0) ?: null;
            $key = $productId.'-'.($variantId ?? 0);
            $price = isset($line['unit_price']) && $line['unit_price'] !== '' ? round((float) $line['unit_price'], 5) : null;

            if (! isset($merged[$key])) {
                $merged[$key] = ['product_id' => $productId, 'product_variant_id' => $variantId, 'quantity' => 0, 'unit_price' => $price];
            } elseif ($merged[$key]['unit_price'] !== $price) {
                throw new RuntimeException('الصنف نفسه مكرر بسعرَي خصم مختلفين؛ اجمعه في سطر واحد.');
            }

            $merged[$key]['quantity'] += (int) $line['quantity'];
        }

        return array_values(array_map(
            fn ($line) => $line['unit_price'] === null ? array_diff_key($line, ['unit_price' => 1]) : $line,
            $merged
        ));
    }
}
