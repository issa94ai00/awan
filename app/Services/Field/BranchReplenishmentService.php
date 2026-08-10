<?php

namespace App\Services\Field;

use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Collection;

/**
 * What a branch needs from the main warehouse, and whether it can have it.
 *
 * Restocking used to be blind in both directions. The seller had no list of what
 * was running out — they browsed the shelf and remembered — and no sight of the
 * main warehouse, so a request was a guess that came back refused after the
 * fact. This class answers both questions before anything is submitted:
 *
 *   - which lines at this branch are out or below their reorder point
 *   - how much of each the main warehouse could actually send today
 *   - how much to ask for
 *
 * The quantity is a *suggestion*, always editable. It exists so the common case
 * is one tap rather than a number typed from memory, not so the app can decide
 * for the person standing in the branch.
 */
class BranchReplenishmentService
{
    /**
     * Lines worth restocking at a branch, most urgent first.
     *
     * @return array<string,mixed>
     */
    public function suggestions(int $branchWarehouseId, ?Warehouse $supply, ?string $search = null): array
    {
        $rows = WarehouseInventory::query()
            ->with('product:id,sku,name_ar,name_en,price,cost_price,barcode')
            ->where('warehouse_id', $branchWarehouseId)
            // Out, or at/below the level the warehouse restocks by. Compared in
            // SQL against the same expression used everywhere else, so a row
            // that the stock screen paints red cannot be missing from here.
            ->whereRaw('available_quantity - reserved_quantity <= COALESCE(reorder_point, 0)')
            ->when($search, fn ($q) => $q->whereHas('product', fn ($p) => $p
                ->where('name_ar', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")))
            ->get();

        $supplyStock = $supply
            ? $this->availabilityAt($supply->id, $rows->pluck('product_id')->all())
            : collect();

        $lines = $rows->map(function (WarehouseInventory $row) use ($supplyStock) {
            $available = $this->sellable($row);
            $reorderPoint = (int) ($row->reorder_point ?? 0);
            $target = $this->targetLevel($row, $reorderPoint);
            $suggested = $this->suggestedQuantity($available, $target);

            $supplyAvailable = (int) ($supplyStock[(int) $row->product_id] ?? 0);

            return [
                'product_id' => (int) $row->product_id,
                'name' => $row->product?->name_ar ?? $row->product?->name_en ?? ('#' . $row->product_id),
                'sku' => $row->product?->sku,
                'barcode' => $row->product?->barcode,
                'available' => $available,
                'reserved' => (int) $row->reserved_quantity,
                'reorder_point' => $reorderPoint,
                // The level worth holding — what the suggestion restocks to.
                'target_level' => $target,
                'suggested_quantity' => $suggested,
                'supply_available' => $supplyAvailable,
                // What the main warehouse could actually send of the suggestion.
                // Shown per row so a seller never submits a line that is going
                // to be refused, and can see it before they tap.
                'supply_covers' => min($suggested, $supplyAvailable),
                'is_covered' => $supplyAvailable >= $suggested,
                // Out is worse than low: nothing can be sold at all.
                'urgency' => $available <= 0 ? 'out' : 'low',
            ];
        });

        // Empty shelves first, then whoever is furthest below their trigger —
        // which is the order a person would work through them.
        $sorted = $lines
            ->sortBy([
                fn ($a, $b) => ($b['urgency'] === 'out' ? 1 : 0) <=> ($a['urgency'] === 'out' ? 1 : 0),
                fn ($a, $b) => ($b['suggested_quantity']) <=> ($a['suggested_quantity']),
            ])
            ->values();

        return [
            'supply_warehouse' => $supply ? [
                'id' => $supply->id,
                'name' => $supply->name,
                'code' => $supply->code,
            ] : null,
            'items' => $sorted->all(),
            'summary' => [
                'total' => $sorted->count(),
                'out_of_stock' => $sorted->where('urgency', 'out')->count(),
                'low_stock' => $sorted->where('urgency', 'low')->count(),
                // Lines the main warehouse cannot fully cover — the seller
                // should know before submitting, not after.
                'not_covered' => $sorted->where('is_covered', false)->count(),
                'suggested_units' => $sorted->sum('suggested_quantity'),
            ],
        ];
    }

    /**
     * Checks a whole request against the source warehouse at once.
     *
     * Every short line is reported together. Failing on the first one meant a
     * seller restocking eight products discovered the shortages one submission
     * at a time, with the whole request rolled back at each attempt.
     *
     * @param  array<int,array{product_id:int,quantity:int}>  $items
     * @return array<int,array<string,mixed>>  the lines the source cannot cover
     */
    public function shortfalls(array $items, int $sourceWarehouseId): array
    {
        $requested = [];
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $requested[$productId] = ($requested[$productId] ?? 0) + max(0, (int) $item['quantity']);
        }

        $available = $this->availabilityAt($sourceWarehouseId, array_keys($requested));

        $names = WarehouseInventory::with('product:id,name_ar,name_en')
            ->where('warehouse_id', $sourceWarehouseId)
            ->whereIn('product_id', array_keys($requested))
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->product_id => $row->product?->name_ar ?? $row->product?->name_en,
            ]);

        $short = [];

        foreach ($requested as $productId => $quantity) {
            $have = (int) ($available[$productId] ?? 0);

            if ($have >= $quantity) {
                continue;
            }

            $short[] = [
                'product_id' => $productId,
                'product_name' => $names[$productId] ?? ('#' . $productId),
                'requested' => $quantity,
                'available' => $have,
                'shortfall' => $quantity - $have,
            ];
        }

        return $short;
    }

    /** A sentence naming every short line, for the refusal message. */
    public function shortfallSummary(array $shortfalls, ?Warehouse $source): string
    {
        $detail = collect($shortfalls)
            ->map(fn ($line) => sprintf(
                '%s (مطلوب %d، متاح %d)',
                $line['product_name'],
                $line['requested'],
                $line['available']
            ))
            ->implode('، ');

        return sprintf(
            'الكمية غير متوفرة في مستودع "%s": %s. عدّل الكميات إلى الحد المتاح ثم أعد الإرسال.',
            $source?->name ?? 'المصدر',
            $detail
        );
    }

    /* ------------------------------------------------------------------ */

    /**
     * The stock level worth holding for a line.
     *
     * The reorder point is a *trigger*, not a target — restocking exactly to it
     * puts the line back on the edge, and it trips again on the next sale. Where
     * the demand figures exist, they give a better answer: cover of the lead
     * time plus the safety stock. The larger of the two is used, so a configured
     * trigger is never undercut by a product that simply has not sold yet.
     */
    private function targetLevel(WarehouseInventory $row, int $reorderPoint): int
    {
        return max($reorderPoint, $row->calculateDynamicReorderPoint());
    }

    /**
     * How much to ask for. Editable — see the class note on why this only has
     * to be a good starting point rather than a correct answer.
     */
    private function suggestedQuantity(int $available, int $target): int
    {
        $gap = $target - $available;

        // Sitting exactly on the trigger, or no target configured at all. Order
        // a trigger's worth rather than nothing, because a row that suggests
        // zero is a row the seller has to think about from scratch — which is
        // the work this screen exists to remove.
        return $gap > 0 ? $gap : max($target, 1);
    }

    /**
     * Sellable units per product at a warehouse.
     *
     * Same definition as InventoryService::reserve() tests, so a line this
     * screen shows as coverable is one the reservation will actually honour.
     *
     * @param  array<int,int>  $productIds
     * @return Collection<int,int>
     */
    private function availabilityAt(int $warehouseId, array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return WarehouseInventory::where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds)
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => $this->sellable($row)]);
    }

    private function sellable(WarehouseInventory $row): int
    {
        return max(0, (int) $row->available_quantity - (int) $row->reserved_quantity);
    }
}
