<?php

namespace App\Services\Field;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Collection;

/**
 * Decides where the goods on a field order actually come from.
 *
 * A rep sells out of the branch they stand in, so an order raised on the phone
 * is served from that branch's shelf and nowhere else. When the shelf falls
 * short, the branch does not simply fail the sale — the main warehouse can make
 * up the difference. This class is the one place that decision is worked out.
 *
 * The rule, in order:
 *
 *   1. Take as much of every line as the seller's own warehouse can give.
 *   2. Whatever is still missing is offered to the main (primary) warehouse.
 *   3. Whatever neither can cover is a genuine shortage, and the order is
 *      refused rather than written down as a promise nobody can keep.
 *
 * Step 2 is deliberately *offered*, not applied. Drawing on the main warehouse
 * commits stock the branch does not hold and obliges someone to move it, so it
 * is the seller's decision, taken with the figures in front of them — hence
 * `plan()` (which writes nothing and is safe to call on every keystroke) being
 * separate from the order that acts on it.
 *
 * ## Why "available" is defined here the way it is
 *
 * Availability is read as `available_quantity - reserved_quantity`, which is
 * exactly what InventoryService::reserve() tests before it will hold anything.
 * The other definition in circulation — `quantity - reserved - damaged -
 * quarantined`, used by the stock-list screens — is a different number, and a
 * plan built on it would offer the seller units that the reservation then
 * refuses, turning a confirmed sale into an error message. A promise made to a
 * customer has to be tested against the thing that will later be asked to
 * honour it.
 */
class BranchOrderSourcingService
{
    /**
     * Works out where each line comes from.
     *
     * Nothing is written and nothing is held — availability read here can be
     * taken by another order a second later, which is why the reservation at
     * confirmation re-checks rather than trusting this.
     *
     * @param  array<int,array{product_id:int,quantity:int}>  $items
     * @return array<string,mixed>
     */
    public function plan(array $items, int $branchWarehouseId, ?Warehouse $supplyWarehouse): array
    {
        // Lines for the same product are answered together. Priced and stocked
        // separately they would each be measured against the full shelf, so two
        // lines of 5 would both pass against 5 units on hand and the order would
        // promise 10.
        $requested = $this->mergeByProduct($items);

        $products = Product::whereIn('id', $requested->keys())->get()->keyBy('id');
        $branchStock = $this->availabilityAt($branchWarehouseId, $requested->keys()->all());
        $supplyStock = $supplyWarehouse
            ? $this->availabilityAt($supplyWarehouse->id, $requested->keys()->all())
            : collect();

        $branch = Warehouse::find($branchWarehouseId);

        $lines = [];
        foreach ($requested as $productId => $quantity) {
            $product = $products->get($productId);

            $branchAvailable = (int) ($branchStock[$productId] ?? 0);
            $fromBranch = min($quantity, $branchAvailable);
            $shortage = $quantity - $fromBranch;

            $supplyAvailable = (int) ($supplyStock[$productId] ?? 0);
            $fromSupply = min($shortage, $supplyAvailable);
            $unavailable = $shortage - $fromSupply;

            $lines[] = [
                'product_id' => (int) $productId,
                'product_name' => $this->productName($product, (int) $productId),
                'sku' => $product?->sku,
                'requested' => $quantity,
                'branch_available' => $branchAvailable,
                'from_branch' => $fromBranch,
                'shortage' => $shortage,
                'supply_available' => $supplyAvailable,
                'from_supply' => $fromSupply,
                // What no warehouse in reach can produce. Non-zero here is the
                // only case that stops an order.
                'unavailable' => $unavailable,
                'needs_supply' => $fromSupply > 0,
                'is_covered' => $unavailable === 0,
            ];
        }

        $needsSupply = collect($lines)->contains(fn ($line) => $line['needs_supply']);
        $blocked = collect($lines)->filter(fn ($line) => $line['unavailable'] > 0)->values();

        return [
            'branch_warehouse' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ] : null,
            'supply_warehouse' => $supplyWarehouse ? [
                'id' => $supplyWarehouse->id,
                'name' => $supplyWarehouse->name,
                'code' => $supplyWarehouse->code,
            ] : null,
            'lines' => $lines,
            // The branch alone covers the order — no prompt, no extra line.
            'is_fully_covered_by_branch' => !$needsSupply && $blocked->isEmpty(),
            'needs_supply' => $needsSupply,
            'can_fulfil' => $blocked->isEmpty(),
            'blocked_lines' => $blocked->all(),
            'supply_summary' => $this->supplySummary($lines, $supplyWarehouse),
            'blocked_summary' => $this->blockedSummary($blocked->all()),
        ];
    }

    /**
     * Turns a plan into the order's lines.
     *
     * A line topped up from the main warehouse becomes *two* lines, not one
     * line with a note: the branch's part, and a second carrying the added
     * quantity and naming where it comes from. That is what the seller, the
     * office and the customer's invoice all end up reading, and it is honest —
     * the two halves are picked in different buildings by different people, and
     * a single line would say otherwise.
     *
     * The split is not merely descriptive. Each line carries a warehouse
     * allocation, which is what SalesOrderWorkflowService holds stock against at
     * confirmation and ships from later, so the paperwork and the goods agree.
     *
     * @param  array<string,mixed>  $plan  from plan()
     * @param  array<int,float>  $discountByProduct  per-product discount, as sent by the client
     * @return array<int,array<string,mixed>>
     */
    public function buildLines(array $plan, array $discountByProduct = []): array
    {
        $supplyName = $plan['supply_warehouse']['name'] ?? null;
        $supplyId = $plan['supply_warehouse']['id'] ?? null;

        $lines = [];

        foreach ($plan['lines'] as $line) {
            $productId = (int) $line['product_id'];
            $discount = (float) ($discountByProduct[$productId] ?? 0);

            // The discount belongs to the line the seller entered, so it stays
            // on the branch part rather than being spread across the split —
            // splitting it would make the same order total out differently
            // depending on how much happened to be on the shelf.
            if ($line['from_branch'] > 0) {
                $lines[] = [
                    'product_id' => $productId,
                    'quantity' => (int) $line['from_branch'],
                    'discount' => $discount,
                    'warehouse_id' => (int) ($plan['branch_warehouse']['id'] ?? 0),
                    'description' => null,
                    'is_supply_line' => false,
                ];
                $discount = 0.0;
            }

            if ($line['from_supply'] > 0 && $supplyId) {
                $lines[] = [
                    'product_id' => $productId,
                    'quantity' => (int) $line['from_supply'],
                    'discount' => $discount,
                    'warehouse_id' => (int) $supplyId,
                    'description' => 'كمية مكمِّلة من ' . $supplyName,
                    'is_supply_line' => true,
                ];
            }
        }

        return $lines;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Requested quantities per product, deduplicated.
     *
     * @param  array<int,array{product_id:int,quantity:int}>  $items
     * @return Collection<int,int>
     */
    private function mergeByProduct(array $items): Collection
    {
        $merged = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $merged[$productId] = ($merged[$productId] ?? 0) + max(0, (int) $item['quantity']);
        }

        return collect($merged);
    }

    /**
     * Sellable units per product at one warehouse.
     *
     * @param  array<int,int>  $productIds
     * @return Collection<int,int>  product id => available
     */
    private function availabilityAt(int $warehouseId, array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return WarehouseInventory::where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds)
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->product_id => max(0, (int) $row->available_quantity - (int) $row->reserved_quantity),
            ]);
    }

    private function productName(?Product $product, int $productId): string
    {
        return $product?->name_ar ?? $product?->name_en ?? $product?->name ?? ('#' . $productId);
    }

    /**
     * The sentence the app puts in front of the seller. Built here rather than
     * in the app so the phone and the server never describe the same shortfall
     * in two different ways.
     *
     * @param  array<int,array<string,mixed>>  $lines
     */
    private function supplySummary(array $lines, ?Warehouse $supplyWarehouse): ?string
    {
        $topped = collect($lines)->filter(fn ($line) => $line['from_supply'] > 0)->values();

        if ($topped->isEmpty() || !$supplyWarehouse) {
            return null;
        }

        // "from_supply", not "shortage": what the main warehouse would actually
        // add. On a line it cannot fully cover the two differ, and naming the
        // gap where the top-up belongs would promise more than is coming.
        $detail = $topped
            ->map(fn ($line) => sprintf(
                '%s (متاح لديك %d من %d، ويُضاف من المستودع الرئيسي %d)',
                $line['product_name'],
                $line['from_branch'],
                $line['requested'],
                $line['from_supply']
            ))
            ->implode('، ');

        return sprintf(
            'الكمية المتاحة في مستودعك لا تكفي: %s. هل ترغب بطلب الكمية الناقصة من "%s"؟ '
                . 'سيُضاف بند مستقل بالكمية المكمِّلة باسم هذا المستودع.',
            $detail,
            $supplyWarehouse->name
        );
    }

    /**
     * @param  array<int,array<string,mixed>>  $blocked
     */
    private function blockedSummary(array $blocked): ?string
    {
        if ($blocked === []) {
            return null;
        }

        $detail = collect($blocked)
            ->map(fn ($line) => sprintf(
                '%s (مطلوب %d، وأقصى ما يمكن تأمينه %d)',
                $line['product_name'],
                $line['requested'],
                $line['from_branch'] + $line['from_supply']
            ))
            ->implode('، ');

        return 'لا يوجد رصيد كافٍ في مستودعك ولا في المستودع الرئيسي: ' . $detail
            . '. عدّل الكميات إلى الحد المتاح ثم أعد المحاولة.';
    }
}
