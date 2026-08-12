<?php

namespace App\Http\Controllers\Api\Field;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\WarehouseInventory;
use App\Services\Field\FieldScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Stock as the field app needs to see it: what is on the shelf here, and what
 * has moved.
 *
 * Every figure comes from `warehouse_inventory`, the same table the WMS and the
 * admin screens read, so a rep and the office never disagree about a quantity.
 * Nothing here writes — stock only ever moves through InventoryService, driven
 * by a document.
 */
class FieldInventoryController extends Controller
{
    /**
     * Stock on hand at a warehouse.
     *
     * `available` is what can actually be sold: quantity less what is reserved
     * for other orders, damaged or quarantined. Selling against `quantity`
     * instead is how two orders end up promising the same unit.
     */
    public function index(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());

        try {
            $warehouseId = $scope->resolveWarehouseId($request->integer('warehouse_id') ?: null);
        } catch (RuntimeException $e) {
            return $this->refuse($e);
        }

        $query = WarehouseInventory::query()
            ->with('product:id,sku,name_ar,name_en,price,cost_price,barcode')
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('*')
            ->withAvailable();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', fn ($p) => $p
                ->where('name_ar', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%"));
        }

        // The two filters a rep standing in a warehouse actually needs.
        $available = WarehouseInventory::availableSql();

        if ($request->boolean('in_stock_only')) {
            $query->whereRaw("({$available}) > 0");
        }

        if ($request->boolean('low_stock_only')) {
            $query->whereRaw("({$available}) <= COALESCE(reorder_point, 0)");
        }

        $rows = $query->orderByDesc('updated_at')->paginate(min((int) $request->input('per_page', 30) ?: 30, 200));

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse_id' => $warehouseId,
                'items' => collect($rows->items())->map(fn ($row) => $this->stockRow($row))->all(),
                'pagination' => $this->pagination($rows),
            ],
        ]);
    }

    /**
     * One product's position across every warehouse the person may see.
     *
     * This is the "can I source it from somewhere else?" question, and it is why
     * the endpoint is not confined to a single warehouse the way the list is.
     *
     * "May see" is wider than "may act on". A rep confined to their branch also
     * sees the main warehouse here, flagged as a supply source, because that is
     * what the shortage prompt on the order screen is built from — a seller
     * cannot sensibly be asked whether to draw on stock they are not allowed to
     * know the size of. It grants nothing: the endpoints that move goods ask
     * FieldScope::warehouseIds(), which still stops at their own branch.
     */
    public function product(Request $request, int $productId): JsonResponse
    {
        $scope = FieldScope::for($request->user());
        $supplyId = $scope->supplyWarehouseId();

        $rows = WarehouseInventory::query()
            ->with(['product:id,sku,name_ar,name_en,price,cost_price', 'warehouse:id,name,code,location_type'])
            ->where('product_id', $productId)
            ->whereIn('warehouse_id', $scope->visibleWarehouseIds())
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => ['product_id' => $productId, 'total_available' => 0, 'warehouses' => []],
            ]);
        }

        $sellable = fn ($row) => $this->available($row);

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $productId,
                'product' => $rows->first()->product,
                // Only what the rep can actually sell. Counting the supply
                // warehouse into this figure would read as "you have this much",
                // which is exactly the confusion the split below exists to avoid.
                'total_available' => $rows
                    ->reject(fn ($r) => (int) $r->warehouse_id === $supplyId)
                    ->sum($sellable),
                'supply_available' => $supplyId
                    ? (int) $rows->where('warehouse_id', $supplyId)->sum($sellable)
                    : 0,
                'warehouses' => $rows->map(fn ($r) => [
                    'warehouse_id' => (int) $r->warehouse_id,
                    'warehouse_name' => $r->warehouse?->name,
                    'quantity' => (int) $r->quantity,
                    'reserved' => (int) $r->reserved_quantity,
                    'available' => $sellable($r),
                    'is_supply_source' => (int) $r->warehouse_id === $supplyId,
                ])->values(),
            ],
        ]);
    }

    /**
     * Movement history — the audit trail of what entered and left.
     *
     * Read straight from `stock_movements`, so it shows every cause: sales,
     * receipts, transfers and manual adjustments alike.
     */
    public function movements(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());

        try {
            $warehouseId = $scope->resolveWarehouseId($request->integer('warehouse_id') ?: null);
        } catch (RuntimeException $e) {
            return $this->refuse($e);
        }

        $query = StockMovement::query()
            ->with(['product:id,sku,name_ar,name_en', 'creator:id,name'])
            ->where('warehouse_id', $warehouseId);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $rows = $query->latest('id')->paginate(min((int) $request->input('per_page', 30) ?: 30, 200));

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse_id' => $warehouseId,
                'movements' => collect($rows->items())->map(fn ($m) => [
                    'id' => $m->id,
                    'product_id' => (int) $m->product_id,
                    'product_name' => $m->product?->name_ar ?? $m->product?->name_en,
                    'sku' => $m->product?->sku,
                    'movement_type' => $m->movement_type,
                    'movement_type_text' => $m->movement_type_text,
                    'quantity' => (int) $m->quantity,
                    'unit_cost' => (float) $m->unit_cost,
                    'total_cost' => (float) $m->total_cost,
                    'reference' => $m->reference,
                    'source' => $m->source,
                    'notes' => $m->notes,
                    'created_by' => $m->creator?->name,
                    'created_at' => $m->created_at?->toDateTimeString(),
                ])->all(),
                'pagination' => $this->pagination($rows),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * What the rep standing in the warehouse may sell — the one definition, so
     * the field app and the back office never quote different figures for the
     * same shelf. See WarehouseInventory::availableSql().
     */
    private function available(WarehouseInventory $row): int
    {
        return $row->available_stock;
    }

    private function stockRow(WarehouseInventory $row): array
    {
        $available = $this->available($row);
        $reorder = (int) ($row->reorder_point ?? 0);

        return [
            'product_id' => (int) $row->product_id,
            'sku' => $row->product?->sku,
            'barcode' => $row->product?->barcode,
            'name' => $row->product?->name_ar ?? $row->product?->name_en,
            'price' => (float) ($row->product?->price ?? 0),
            'quantity' => (int) $row->quantity,
            'reserved' => (int) $row->reserved_quantity,
            'available' => $available,
            'reorder_point' => $reorder,
            // Precomputed so every client shows the same colour for the same row.
            'stock_status' => $available <= 0 ? 'out' : ($available <= $reorder ? 'low' : 'ok'),
            'updated_at' => $row->updated_at?->toDateTimeString(),
        ];
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    private function refuse(RuntimeException $e): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 403);
    }
}
