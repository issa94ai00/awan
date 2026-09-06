<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The stocked items that have no cost price, and a way to give them one.
 *
 * The accounting health check counts them and says "enter the cost price of
 * these items from the inventory screen" — a number and an errand. It never
 * named which items, so acting on it meant leaving the page, opening the
 * inventory list and working out for yourself which rows it had meant.
 *
 * This names them and takes the answer. It is deliberately not part of
 * systemHealth(): that endpoint is read-only so that repairs stay something
 * a person chooses, and a report that quietly writes is a report nobody can
 * trust. Pricing is that deliberate step, asked for and confirmed.
 *
 * Only `cost_price` is writable here, and only for products the check is
 * actually complaining about. A screen that exists to clear one specific
 * finding should not double as a way to rewrite arbitrary product data.
 */
class UnpricedStockController extends Controller
{
    /**
     * The same population the `products_without_cost` check counts: anything
     * physically on a shelf whose cost is unknown. Stock is what makes it
     * matter — an unpriced item nobody holds costs the books nothing.
     */
    private function unpricedQuery()
    {
        return Product::query()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('warehouse_inventory')
                ->whereColumn('warehouse_inventory.product_id', 'products.id')
                ->where('warehouse_inventory.quantity', '>', 0))
            ->where(fn ($q) => $q->whereNull('cost_price')->orWhere('cost_price', '<=', 0));
    }

    public function index(): JsonResponse
    {
        $products = $this->unpricedQuery()
            ->select('id', 'name_ar', 'name_en', 'sku', 'price', 'cost_price', 'unit')
            ->withSum(['inventory as stock_on_hand' => fn ($q) => $q->where('quantity', '>', 0)], 'quantity')
            ->orderBy('name_ar')
            ->get();

        $suggestions = $this->lastPurchaseCosts($products->pluck('id')->all());

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name_ar ?: $product->name_en,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'price' => (float) $product->price,
                    'cost_price' => (float) $product->cost_price,
                    'stock_on_hand' => (float) ($product->stock_on_hand ?? 0),
                    // What this item last actually cost to buy. The one number
                    // that answers the question the screen is asking, when a
                    // purchase for it exists; null is honest when none does.
                    'suggested_cost' => $suggestions[$product->id] ?? null,
                ])->values(),
            ],
        ]);
    }

    /**
     * The unit price on the most recent receipt of each product.
     *
     * Receipts before orders: an order says what was agreed, a receipt says
     * what arrived and was billed. Where both exist the receipt is the truer
     * cost.
     *
     * @param  list<int>  $productIds
     * @return array<int,float>
     */
    private function lastPurchaseCosts(array $productIds): array
    {
        if (! $productIds) {
            return [];
        }

        $costs = [];

        $received = DB::table('purchase_receipt_items as i')
            ->join('purchase_receipts as r', 'r.id', '=', 'i.purchase_receipt_id')
            ->whereIn('i.product_id', $productIds)
            ->where('i.unit_price', '>', 0)
            ->orderBy('r.receipt_date')
            ->orderBy('i.id')
            ->pluck('i.unit_price', 'i.product_id');

        $ordered = DB::table('purchase_order_items')
            ->whereIn('product_id', $productIds)
            ->where('unit_price', '>', 0)
            ->orderBy('id')
            ->pluck('unit_price', 'product_id');

        // pluck() keyed by product keeps the last row it walks, and both
        // queries are ordered oldest-first, so each key holds the most recent.
        foreach ($ordered as $productId => $unitPrice) {
            $costs[(int) $productId] = (float) $unitPrice;
        }

        foreach ($received as $productId => $unitPrice) {
            $costs[(int) $productId] = (float) $unitPrice;
        }

        return $costs;
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:products,id',
            'items.*.cost_price' => 'required|numeric|min:0.00001',
        ], [
            'items.required' => 'لم تُرسل أي أصناف للتسعير.',
            'items.*.cost_price.required' => 'سعر التكلفة مطلوب.',
            'items.*.cost_price.min' => 'سعر التكلفة يجب أن يكون أكبر من صفر، وإلا بقي الصنف بلا تكلفة.',
        ]);

        // Whatever the request names, only products the check is currently
        // reporting may be written — the payload chooses among them, it does
        // not widen them.
        $eligible = $this->unpricedQuery()->pluck('id')->flip();

        $applied = 0;
        $skipped = [];

        DB::transaction(function () use ($validated, $eligible, &$applied, &$skipped) {
            foreach ($validated['items'] as $item) {
                if (! $eligible->has((int) $item['id'])) {
                    $skipped[] = (int) $item['id'];

                    continue;
                }

                Product::whereKey($item['id'])->update(['cost_price' => $item['cost_price']]);
                $applied++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => $applied > 0
                ? 'تم تسعير '.$applied.' صنف.'
                : 'لم يتغيّر شيء.',
            'data' => [
                'updated' => $applied,
                // What the health check would now report, so the caller can
                // show the finding shrink instead of re-running everything.
                'remaining' => $this->unpricedQuery()->count(),
                // Named, not swallowed: a product priced by someone else while
                // this dialog was open is no longer eligible, and saying so
                // beats silently dropping it.
                'skipped' => $skipped,
            ],
        ]);
    }
}
