<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\ProductExcelService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the inventory screens.
 *
 * Every figure here is derived from warehouse_inventory (the per-warehouse
 * position the WMS works from) rather than from products.stock_quantity, so the
 * dashboard, the stock table and the movement log all agree with each other.
 */
class InventoryController extends Controller
{
    public function summary(): JsonResponse
    {
        // The stored `cost_basis` column is the costing-method enum ("FIFO"), not a
        // number, so multiplying the quantity by it evaluated to zero and the
        // overview card always read 0 ر.س. The value is priced off the product
        // instead — selling price, falling back to cost when a product has not been
        // priced yet — which is the same rule the stock table's value column uses,
        // so the card equals the sum of the rows underneath it.
        $unitPrice = 'COALESCE(NULLIF(products.price, 0), products.cost_price, 0)';

        $totals = WarehouseInventory::query()
            ->leftJoin('products', 'products.id', '=', 'warehouse_inventory.product_id')
            ->selectRaw('COUNT(DISTINCT warehouse_inventory.product_id) as products_with_stock')
            ->selectRaw('COALESCE(SUM(warehouse_inventory.quantity), 0) as total_quantity')
            ->selectRaw("COALESCE(SUM(warehouse_inventory.quantity * {$unitPrice}), 0) as total_value")
            ->selectRaw('SUM(CASE WHEN warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity > 0 THEN 1 ELSE 0 END) as in_stock_rows')
            ->selectRaw('SUM(CASE WHEN warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity <= COALESCE(warehouse_inventory.reorder_point, 0) AND warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity > 0 THEN 1 ELSE 0 END) as low_stock_rows')
            ->selectRaw('SUM(CASE WHEN warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock_rows')
            ->first();

        $movementsToday = StockMovement::whereDate('created_at', today())->count();

        $movementTotals = StockMovement::query()
            ->whereDate('created_at', today())
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END), 0) as received")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END), 0) as issued")
            ->first();

        $warehouses = Warehouse::query()
            ->select('warehouses.id', 'warehouses.name', 'warehouses.code', 'warehouses.is_active')
            ->selectRaw('COALESCE(SUM(warehouse_inventory.quantity), 0) as total_quantity')
            ->leftJoin('warehouse_inventory', 'warehouse_inventory.warehouse_id', '=', 'warehouses.id')
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.code', 'warehouses.is_active')
            ->orderBy('warehouses.id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'products_with_stock' => (int) ($totals->products_with_stock ?? 0),
                    'total_quantity' => (int) ($totals->total_quantity ?? 0),
                    'total_value' => round((float) ($totals->total_value ?? 0), 2),
                    'in_stock_rows' => (int) ($totals->in_stock_rows ?? 0),
                    'low_stock_rows' => (int) ($totals->low_stock_rows ?? 0),
                    'out_of_stock_rows' => (int) ($totals->out_of_stock_rows ?? 0),
                ],
                'today' => [
                    'movements' => (int) $movementsToday,
                    'received' => (int) ($movementTotals->received ?? 0),
                    'issued' => (int) ($movementTotals->issued ?? 0),
                ],
                'warehouses' => $warehouses,
            ],
        ]);
    }

    public function stock(Request $request): JsonResponse
    {
        $query = WarehouseInventory::query()
            ->with(['product', 'warehouse', 'bin'])
            ->select('warehouse_inventory.*')
            ->selectRaw('warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity as available')
            ->leftJoin('products', 'products.id', '=', 'warehouse_inventory.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'warehouse_inventory.warehouse_id');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_inventory.warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('product_id')) {
            $query->where('warehouse_inventory.product_id', $request->product_id);
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'low' => $query->where(function ($q) {
                    $q->whereRaw('warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity <= COALESCE(warehouse_inventory.reorder_point, 0)')
                        ->whereRaw('warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity > 0');
                }),
                'out' => $query->whereRaw('warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity <= 0'),
                default => null,
            };
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.name_ar', 'like', "%{$search}%")
                    ->orWhere('products.name_en', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.barcode', 'like', "%{$search}%");
            });
        }

        $orderBy = $request->input('sort_by', 'warehouse_inventory.updated_at');
        $direction = $request->input('sort_dir', 'desc');

        if (in_array($orderBy, ['quantity', 'available', 'reserved_quantity', 'reorder_point', 'updated_at'], true)) {
            $query->orderBy($orderBy === 'available' ? 'available' : 'warehouse_inventory.' . $orderBy, $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('warehouse_inventory.updated_at');
        }

        $rows = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'stock' => $rows->items(),
                'pagination' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'has_more_pages' => $rows->hasMorePages(),
                ],
            ],
        ]);
    }

    public function export(Request $request, ProductExcelService $excel): Response
    {
        $query = WarehouseInventory::query()
            ->with(['product', 'warehouse'])
            ->select('warehouse_inventory.*')
            ->leftJoin('products', 'products.id', '=', 'warehouse_inventory.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'warehouse_inventory.warehouse_id');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_inventory.warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.name_ar', 'like', "%{$search}%")
                    ->orWhere('products.name_en', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'low' => $query->where(function ($q) {
                    $q->whereRaw('warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity <= COALESCE(warehouse_inventory.reorder_point, 0)')
                        ->whereRaw('warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity > 0');
                }),
                'out' => $query->whereRaw('warehouse_inventory.quantity - warehouse_inventory.reserved_quantity - warehouse_inventory.damaged_quantity - warehouse_inventory.quarantined_quantity <= 0'),
                default => null,
            };
        }

        $inventory = $query->orderBy('warehouses.name')
            ->orderBy('products.name_ar')
            ->get();

        $binary = $excel->exportWarehouseInventory($inventory);
        $filename = 'warehouse-stock-' . now()->format('Y-m-d') . '.xlsx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function import(Request $request, ProductExcelService $excel): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            return response()->json([
                'success' => false,
                'message' => 'The file must be an xlsx file.',
                'data' => null,
            ], 422);
        }

        $result = $excel->importStockFile($file);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse stock imported successfully',
            'data' => $result,
        ]);
    }
}
