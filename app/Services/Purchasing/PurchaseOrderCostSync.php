<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use Illuminate\Support\Facades\DB;

/**
 * What a purchase order's goods actually cost to land, written back onto it.
 *
 * A purchase order is a promise to buy at a price. What the goods cost is
 * settled later and elsewhere: the quantity that actually arrived is on the
 * receipt, and the price it landed at is on the stock layers the receipt
 * opened — which a landed-cost allocation raises afterwards, by the freight,
 * customs and insurance the order itself never mentions.
 *
 * Purchase reporting read none of that. It costed an order by multiplying
 * ordered quantity against ordered price, so a short delivery still reported
 * the full spend, a price renegotiated at the door was ignored, and every
 * penny of freight was invisible — which in turn overstated the planned margin
 * of everything bought, by the whole landed cost.
 *
 * This recomputes an order's received figures from its receipts and stamps
 * them on its lines. It is idempotent and always works from the whole set of
 * receipts, so a second delivery, a correction or a late landed cost all
 * arrive at the same answer as a clean run.
 */
class PurchaseOrderCostSync
{
    /** Syncs the order a receipt was booked against, if it names one. */
    public function syncFromReceipt(PurchaseReceipt $receipt): void
    {
        if (! $receipt->purchase_order_id) {
            return;
        }

        $order = PurchaseOrder::with('items')->find($receipt->purchase_order_id);

        if ($order) {
            $this->sync($order);
        }
    }

    public function sync(PurchaseOrder $order): void
    {
        $order->loadMissing('items');

        $received = $this->receivedByLine($order);

        if ($received === []) {
            return;
        }

        // Ordered quantity per line key, so a product spread over two lines of
        // one order can be given its share of a delivery that names only the
        // product. Receipts do not point back at the line they fill.
        $orderedByKey = [];
        foreach ($order->items as $item) {
            $key = self::lineKey($item->product_id, $item->product_variant_id);
            $orderedByKey[$key] = ($orderedByKey[$key] ?? 0) + (int) $item->quantity;
        }

        foreach ($order->items as $item) {
            $key = self::lineKey($item->product_id, $item->product_variant_id);
            $delivery = $received[$key] ?? null;

            if ($delivery === null) {
                continue;
            }

            $ordered = $orderedByKey[$key] ?? 0;
            $share = $ordered > 0 ? (int) $item->quantity / $ordered : 1;

            $quantity = round($delivery['quantity'] * $share, 5);
            $cost = round($delivery['cost'] * $share, 5);

            $item->forceFill([
                'received_quantity' => (int) round($quantity),
                'received_cost' => $cost,
                'received_unit_cost' => $quantity > 0 ? round($cost / $quantity, 5) : 0,
            ])->save();
        }
    }

    /**
     * Which order line a product or variant settles against: "12:40" for
     * variant 40 of product 12, "12" for the product itself.
     */
    public static function lineKey($productId, $variantId = null): string
    {
        return $variantId ? $productId.':'.$variantId : (string) $productId;
    }

    /**
     * Everything delivered against this order, by the order line it fills.
     *
     * A receipt line for a variant fills the order's line for that variant;
     * one the order only asked for as the product (or a receipt that names no
     * variant) fills the product's line, as it always has.
     *
     * The price is the one the stock layers hold rather than the one on the
     * receipt line: a landed-cost allocation raises the layers and leaves the
     * receipt saying what the supplier charged, so the layer is the only place
     * that knows what the goods are actually worth on the shelf. Layers are
     * per product, so where one receipt brings in two variants at different
     * prices each keeps its own price, raised by the product's landed uplift.
     *
     * @return array<string,array{quantity: float, cost: float}>
     */
    private function receivedByLine(PurchaseOrder $order): array
    {
        $receipts = PurchaseReceipt::with('items')
            ->where('purchase_order_id', $order->id)
            ->get();

        if ($receipts->isEmpty()) {
            return [];
        }

        $orderKeys = [];
        foreach ($order->items as $item) {
            $orderKeys[self::lineKey($item->product_id, $item->product_variant_id)] = true;
        }

        $landed = $this->landedUnitCosts($receipts->pluck('receipt_number')->filter()->all());

        $received = [];

        foreach ($receipts as $receipt) {
            // What the supplier charged on average per product on this
            // receipt, against which the layers' landed cost is an uplift.
            $charged = [];
            foreach ($receipt->items as $line) {
                if ($line->product_id && (float) $line->quantity > 0) {
                    $charged[$line->product_id]['quantity'] = ($charged[$line->product_id]['quantity'] ?? 0) + (float) $line->quantity;
                    $charged[$line->product_id]['cost'] = ($charged[$line->product_id]['cost'] ?? 0) + (float) $line->quantity * (float) $line->unit_price;
                }
            }

            foreach ($receipt->items as $line) {
                if (! $line->product_id) {
                    continue;
                }

                $quantity = (float) $line->quantity;

                if ($quantity <= 0) {
                    continue;
                }

                // No layer means the goods were taken in before layering, or by
                // a path that opened none; the receipt's own price is then the
                // best statement of what they cost.
                $unitCost = (float) $line->unit_price;
                $landedUnit = $landed[$receipt->receipt_number.':'.$line->product_id] ?? null;
                if ($landedUnit !== null) {
                    $average = ($charged[$line->product_id]['cost'] ?? 0) / max(1, $charged[$line->product_id]['quantity'] ?? 1);
                    $unitCost = $average > 0 ? $unitCost * ($landedUnit / $average) : $landedUnit;
                }

                $key = self::lineKey($line->product_id, $line->product_variant_id);
                if (! isset($orderKeys[$key])) {
                    $key = self::lineKey($line->product_id);
                }

                $received[$key] = [
                    'quantity' => ($received[$key]['quantity'] ?? 0) + $quantity,
                    'cost' => ($received[$key]['cost'] ?? 0) + $unitCost * $quantity,
                ];
            }
        }

        return $received;
    }

    /**
     * The landed unit cost of each (receipt, product), from the layers that
     * receipt opened — weighted by layer size, since one delivery can open
     * several layers and a landed cost is spread over all of them.
     *
     * @param  list<string>  $receiptNumbers
     * @return array<string,float>
     */
    private function landedUnitCosts(array $receiptNumbers): array
    {
        if ($receiptNumbers === []) {
            return [];
        }

        $rows = DB::table('inventory_cost_layers')
            ->where('source', 'purchase_receipt')
            ->whereIn('reference', $receiptNumbers)
            ->select('reference', 'product_id')
            ->selectRaw('SUM(received_quantity) as quantity')
            ->selectRaw('SUM(received_quantity * unit_cost) as cost')
            ->groupBy('reference', 'product_id')
            ->get();

        $costs = [];

        foreach ($rows as $row) {
            $quantity = (float) $row->quantity;

            if ($quantity > 0) {
                $costs[$row->reference.':'.$row->product_id] = (float) $row->cost / $quantity;
            }
        }

        return $costs;
    }
}
