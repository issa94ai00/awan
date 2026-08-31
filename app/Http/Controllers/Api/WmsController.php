<?php

namespace App\Http\Controllers\Api;

use App\Events\StockAlert;
use App\Events\StockMovementCreated;
use App\Http\Controllers\Controller;
use App\Models\CycleCount;
use App\Models\PackingList;
use App\Models\PackingListItem;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\Product;
use App\Models\ProductWarehouseAssignment;
use App\Models\SalesOrder;
use App\Models\ShippingManifest;
use App\Models\ShippingManifestItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryCostingService;
use App\Services\Inventory\InventoryService;
use App\Services\PackingService;
use App\Services\PickingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WmsController extends Controller
{
    protected PickingService $pickingService;

    protected PackingService $packingService;

    public function __construct(PickingService $pickingService, PackingService $packingService)
    {
        $this->pickingService = $pickingService;
        $this->packingService = $packingService;
    }

    // ==================== Dashboard ====================

    public function dashboard(Request $request)
    {
        $stats = [
            'linked_products' => WarehouseInventory::distinct('product_id')->count(),
            'total_products' => Product::count(),
            'active_warehouses' => Warehouse::where('is_active', true)->count(),
            'total_warehouses' => Warehouse::count(),
            // Measured against what can actually be sold. Comparing the raw
            // bucket meant a product whose entire balance was reserved still
            // looked comfortably stocked and raised no reorder alert.
            'reorder_products' => WarehouseInventory::whereRaw(
                '('.WarehouseInventory::availableSql().') <= COALESCE(reorder_point, 0)'
            )->count(),
            'total_stock' => WarehouseInventory::sum('quantity'),
            // Priced off the real FIFO layers, not the `cost_basis` column —
            // that column holds the FIFO/FEFO/LIFO costing-method enum, not a
            // number (see InventoryController::summary()).
            'total_value' => app(InventoryCostingService::class)->valueOnHand(),
            'today_movements' => StockMovement::whereDate('created_at', today())->count(),
            'active_users' => StockMovement::whereDate('created_at', today())
                ->whereNotNull('created_by')
                ->distinct('created_by')
                ->count('created_by'),
        ];

        $warehousesWithCapacity = Warehouse::withSum('inventory as total_stock', 'quantity')
            ->where('capacity', '>', 0)
            ->get();
        $stats['avg_utilization'] = $warehousesWithCapacity->isNotEmpty()
            ? round($warehousesWithCapacity->avg(fn ($w) => min(100, (($w->total_stock ?? 0) / $w->capacity) * 100)), 1)
            : 0;

        // "Consumed" means shipped out, not sitting on the shelf — summing
        // current inventory here (as this used to) ranked products by how
        // much stock they're holding, which is closer to the opposite of
        // consumption. Real issue movements over the last 30 days instead.
        $topProducts = StockMovement::where('movement_type', StockMovement::TYPE_OUT)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('product_id, SUM(quantity) as total_consumption')
            ->groupBy('product_id')
            ->orderByDesc('total_consumption')
            ->limit(5)
            ->with('product:id,name_ar,name_en')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->product_id,
                    'name' => $row->product?->name ?? '-',
                    'consumption' => (int) $row->total_consumption,
                ];
            });

        $warehouseDistribution = Warehouse::withSum('inventory as total_stock', 'quantity')
            ->where('is_active', true)
            ->get()
            ->map(function ($warehouse) use ($stats) {
                $percentage = $stats['total_stock'] > 0
                    ? ($warehouse->total_stock / $stats['total_stock']) * 100
                    : 0;

                return [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'percentage' => round($percentage, 1),
                ];
            });

        $alerts = WarehouseInventory::whereRaw(
            '('.WarehouseInventory::availableSql().') <= COALESCE(reorder_point, 0)'
        )
            ->with(['product', 'warehouse'])
            ->limit(10)
            ->get()
            ->map(function ($inventory) {
                return [
                    'id' => $inventory->id,
                    'product_id' => $inventory->product_id,
                    'message' => "المنتج {$inventory->product->name} في المستودع {$inventory->warehouse->name} وصل للحد الأدنى",
                    'created_at' => $inventory->updated_at->format('Y-m-d H:i:s'),
                ];
            });

        $recentMovements = StockMovement::with(['product:id,name_ar,name_en', 'warehouse:id,name'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'date' => $movement->created_at->format('Y-m-d H:i'),
                    // Product has no `name` column — it's a computed accessor
                    // over name_ar/name_en — so the eager-load above selects
                    // those instead; the plain `->name` here still resolves
                    // through the accessor once they're loaded.
                    'product' => $movement->product?->name ?? '-',
                    'warehouse' => $movement->warehouse?->name ?? '-',
                    'type' => $movement->movement_type,
                    'type_text' => $movement->movement_type_text,
                    'quantity' => $movement->quantity,
                ];
            });

        return response()->json([
            'stats' => $stats,
            'top_products' => $topProducts,
            'warehouse_distribution' => $warehouseDistribution,
            'recent_movements' => $recentMovements,
            'alerts' => $alerts,
        ]);
    }

    // ==================== Products ====================

    public function indexProducts(Request $request)
    {
        $query = Product::with(['category', 'inventory.warehouse']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('code', 'like', '%'.$request->search.'%')
                    ->orWhere('sku', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->category) {
            $query->where('category_id', $request->category);
        }

        // Was an unbounded ->get() — every product, with every warehouse's
        // inventory row, on every request. Same per_page cap the rest of the
        // module uses; callers that genuinely need the whole catalog (Stock
        // Organization's assigned/unassigned view) ask for a larger page
        // explicitly rather than the server handing out everything by default.
        $perPage = min((int) $request->input('per_page', 20) ?: 20, 500);
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(function ($product) {
            return [
                'id' => $product->id,
                'code' => $product->code ?? 'N/A',
                'name' => $product->name,
                'sku' => $product->sku ?? 'N/A',
                'category' => $product->category,
                'unit' => $product->unit,
                'price' => $product->price,
                'min_stock' => $product->reorder_point ?? 0,
                'max_stock' => $product->max_stock ?? 0,
                'warehouses_count' => $product->inventory ? $product->inventory->count() : 0,
                'total_balance' => $product->inventory ? $product->inventory->sum('quantity') : 0,
            ];
        });

        return response()->json($products);
    }

    // ==================== Assignments ====================

    public function indexAssignments(Request $request)
    {
        $query = ProductWarehouseAssignment::with(['product', 'warehouse', 'primaryBin', 'supplier', 'inventory']);

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Search by product name or code
        if ($request->filled('search')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('code', 'like', '%'.$request->search.'%')
                    ->orWhere('sku', 'like', '%'.$request->search.'%');
            });
        }

        $perPage = $request->input('per_page', 15);
        $assignments = $query->latest()->paginate($perPage);

        $assignments->getCollection()->transform(function ($assignment) {
            $inventory = $assignment->inventory->first();
            $product = $assignment->product;

            return [
                'id' => $assignment->id,
                'product_id' => $assignment->product_id,
                'warehouse_id' => $assignment->warehouse_id,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'cost_price' => $product->cost_price,
                ],
                'warehouse' => [
                    'id' => $assignment->warehouse->id,
                    'name' => $assignment->warehouse->name,
                    'code' => $assignment->warehouse->code,
                ],
                'quantity' => $inventory ? $inventory->quantity : 0,
                // Net of what is already promised — the raw bucket counted
                // reserved units as available.
                'available_quantity' => $inventory ? $inventory->available_stock : 0,
                'reserved_quantity' => $inventory ? $inventory->reserved_quantity : 0,
                'min_stock_level' => $assignment->min_stock_level,
                'max_stock_level' => $assignment->max_stock_level,
                'safety_stock' => $assignment->safety_stock,
                // `warehouse_inventory.cost_basis` is the FIFO/FEFO/LIFO
                // costing-method enum, not a price — the product's cost_price
                // is the correct figure to show here.
                'cost_price' => $product->cost_price,
                'primary_bin_id' => $assignment->primary_bin_id,
                'primary_bin_code' => $assignment->primaryBin ? $assignment->primaryBin->code : '',
                'replenishment_method' => $assignment->replenishment_method,
                'planning_method' => $assignment->planning_method,
                'lead_time_days' => $assignment->lead_time_days,
                'supplier_id' => $assignment->supplier_id,
                'supplier_name' => $assignment->supplier ? $assignment->supplier->name : '',
                'is_active' => $assignment->is_active,
                'effective_date' => $assignment->effective_date?->format('Y-m-d'),
                'expiry_date' => $assignment->expiry_date?->format('Y-m-d'),
                'auto_reorder_enabled' => $assignment->auto_reorder_enabled,
                'notes' => $assignment->notes,
            ];
        });

        return response()->json($assignments);
    }

    public function showAssignment($id)
    {
        $assignment = ProductWarehouseAssignment::with(['product', 'warehouse', 'primaryBin', 'supplier', 'inventory', 'binAssignments.bin'])
            ->findOrFail($id);

        $inventory = $assignment->inventory->first();
        $product = $assignment->product;

        return response()->json([
            'data' => [
                'id' => $assignment->id,
                'product_id' => $assignment->product_id,
                'warehouse_id' => $assignment->warehouse_id,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'name_ar' => $product->name_ar,
                    'name_en' => $product->name_en,
                    'code' => $product->code,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'cost_price' => $product->cost_price,
                    'unit' => $product->unit,
                    'weight' => $product->weight,
                    'length' => $product->length,
                    'width' => $product->width,
                    'height' => $product->height,
                ],
                'warehouse' => [
                    'id' => $assignment->warehouse->id,
                    'name' => $assignment->warehouse->name,
                    'code' => $assignment->warehouse->code,
                    'location_type' => $assignment->warehouse->location_type,
                    'address' => $assignment->warehouse->address,
                ],
                'quantity' => $inventory ? $inventory->quantity : 0,
                // Net of what is already promised — the raw bucket counted
                // reserved units as available.
                'available_quantity' => $inventory ? $inventory->available_stock : 0,
                'reserved_quantity' => $inventory ? $inventory->reserved_quantity : 0,
                'damaged_quantity' => $inventory ? $inventory->damaged_quantity : 0,
                'quarantined_quantity' => $inventory ? $inventory->quarantined_quantity : 0,
                'min_stock_level' => $assignment->min_stock_level,
                'max_stock_level' => $assignment->max_stock_level,
                'safety_stock' => $assignment->safety_stock,
                // `warehouse_inventory.cost_basis` is the FIFO/FEFO/LIFO
                // costing-method enum, not a price — the product's cost_price
                // is the correct figure to show here.
                'cost_price' => $product->cost_price,
                'primary_bin_id' => $assignment->primary_bin_id,
                'primary_bin_code' => $assignment->primaryBin ? $assignment->primaryBin->code : '',
                'replenishment_method' => $assignment->replenishment_method,
                'planning_method' => $assignment->planning_method,
                'lead_time_days' => $assignment->lead_time_days,
                'supplier_id' => $assignment->supplier_id,
                'supplier_name' => $assignment->supplier ? $assignment->supplier->name : '',
                'is_active' => $assignment->is_active,
                'effective_date' => $assignment->effective_date?->format('Y-m-d'),
                'expiry_date' => $assignment->expiry_date?->format('Y-m-d'),
                'auto_reorder_enabled' => $assignment->auto_reorder_enabled,
                'putaway_strategy' => $assignment->putaway_strategy,
                'notes' => $assignment->notes,
                'bin_assignments' => $assignment->binAssignments->map(function ($binAssignment) {
                    return [
                        'id' => $binAssignment->id,
                        'bin_id' => $binAssignment->bin_id,
                        'bin_code' => $binAssignment->bin?->code,
                        'bin_zone' => $binAssignment->bin?->zone,
                        'capacity_percentage' => $binAssignment->capacity_percentage,
                    ];
                }),
            ],
        ]);
    }

    public function updateAssignment(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:0',
            'min_stock_level' => 'nullable|integer|min:0',
            'max_stock_level' => 'nullable|integer|gt:min_stock_level',
            'safety_stock' => 'nullable|integer|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'primary_bin_id' => 'nullable|exists:warehouse_bins,id',
            'replenishment_method' => 'nullable|in:purchase,manufacture,internal_distribution,warehouse_transfer',
            'planning_method' => 'nullable|in:rop,mrp',
            'lead_time_days' => 'nullable|integer|min:1',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'is_active' => 'nullable|boolean',
            'auto_reorder_enabled' => 'nullable|boolean',
            'putaway_strategy' => 'nullable|in:fifo,fefo,similarity,weight_based,volume_based',
            'notes' => 'nullable|string',
        ]);

        $assignment = ProductWarehouseAssignment::findOrFail($id);

        // Update assignment
        $assignment->update([
            'min_stock_level' => $request->min_stock_level ?? $assignment->min_stock_level,
            'max_stock_level' => $request->max_stock_level ?? $assignment->max_stock_level,
            'safety_stock' => $request->safety_stock ?? $assignment->safety_stock,
            'primary_bin_id' => $request->primary_bin_id,
            'replenishment_method' => $request->replenishment_method ?? $assignment->replenishment_method,
            'planning_method' => $request->planning_method ?? $assignment->planning_method,
            'lead_time_days' => $request->lead_time_days ?? $assignment->lead_time_days,
            'supplier_id' => $request->supplier_id,
            'is_active' => $request->is_active ?? $assignment->is_active,
            'auto_reorder_enabled' => $request->auto_reorder_enabled ?? $assignment->auto_reorder_enabled,
            'putaway_strategy' => $request->putaway_strategy ?? $assignment->putaway_strategy,
            'notes' => $request->notes,
        ]);

        // Update inventory if quantity provided
        //
        // The number on the form is a count of what is actually on the shelf, so
        // it is booked as an adjustment: the difference is applied to the
        // warehouse row through InventoryService (which also writes the movement
        // and keeps products.stock_quantity in step), keyed per assignment so a
        // resubmit cannot move stock twice.
        if ($request->filled('quantity')) {
            $inventory = app(InventoryService::class);
            $warehouseRow = WarehouseInventory::where('product_id', $assignment->product_id)
                ->where('warehouse_id', $assignment->warehouse_id)
                ->first();

            $newQuantity = (int) $request->quantity;
            $difference = $newQuantity - (int) ($warehouseRow?->quantity ?? 0);

            if ($warehouseRow && $difference !== 0) {
                $inventory->adjust($assignment->product_id, $difference, $assignment->warehouse_id, [
                    'key' => 'assign:'.$assignment->id,
                    'source' => 'assignment',
                    'reference' => $assignment->id,
                    'reason' => 'تحديث رصيد التعيين من شاشة المخزون',
                    'allow_negative' => true,
                    'created_by' => auth()->id(),
                ]);

                $warehouseRow->refresh();
            }

            // `cost_price` submitted here used to be written into
            // `warehouse_inventory.cost_basis` — that column is the
            // FIFO/FEFO/LIFO costing-method enum, not a price, so that write
            // silently corrupted the row's costing strategy and never fed
            // real costing (InventoryCostingService reads from
            // inventory_cost_layers). Removed rather than replaced: this
            // screen has no per-warehouse cost override today.

            if (! $warehouseRow && $newQuantity > 0) {
                $inventory->receive($assignment->product_id, $newQuantity, $assignment->warehouse_id, [
                    'key' => 'assign:'.$assignment->id.':opening',
                    'source' => 'assignment',
                    'reference' => $assignment->id,
                    'reason' => 'رصيد افتتاحي للتعيين',
                    'unit_cost' => $request->cost_price ?? 0,
                    'created_by' => auth()->id(),
                ]);
            }
        }

        return response()->json([
            'message' => 'تم تحديث التعيين بنجاح',
            'data' => $assignment->load(['product', 'warehouse', 'primaryBin', 'supplier']),
        ]);
    }

    public function destroyAssignment($id)
    {
        $assignment = ProductWarehouseAssignment::findOrFail($id);

        // Check if there's inventory
        $inventory = WarehouseInventory::where('product_id', $assignment->product_id)
            ->where('warehouse_id', $assignment->warehouse_id)
            ->first();

        if ($inventory && $inventory->quantity > 0) {
            return response()->json([
                'message' => 'لا يمكن حذف التعيين لأنه يحتوي على مخزون',
            ], 400);
        }

        $assignment->delete();

        return response()->json([
            'message' => 'تم حذف التعيين بنجاح',
        ]);
    }

    public function storeAssignment(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'replenishment_method' => 'required|in:purchase,manufacture,internal_distribution,warehouse_transfer',
            'planning_method' => 'required|in:rop,mrp',
            'min_stock_level' => 'required|integer|min:0',
            'max_stock_level' => 'required|integer|gt:min_stock_level',
            'safety_stock' => 'required|integer|min:0',
            'lead_time_days' => 'required|integer|min:1',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'primary_bin_id' => 'nullable|exists:warehouse_bins,id',
            'putaway_strategy' => 'nullable|in:fifo,fefo,similarity,weight_based,volume_based',
            'auto_reorder_enabled' => 'nullable|boolean',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'notes' => 'nullable|string',
        ]);

        // Check if assignment already exists
        $existing = ProductWarehouseAssignment::where('product_id', $request->product_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'هذا المنتج مرتبط بالفعل بهذا المستودع',
                'data' => $existing,
            ], 400);
        }

        // Create assignment
        $assignment = ProductWarehouseAssignment::create([
            'product_id' => $request->product_id,
            'warehouse_id' => $request->warehouse_id,
            'replenishment_method' => $request->replenishment_method,
            'planning_method' => $request->planning_method,
            'min_stock_level' => $request->min_stock_level,
            'max_stock_level' => $request->max_stock_level,
            'safety_stock' => $request->safety_stock,
            'lead_time_days' => $request->lead_time_days,
            'supplier_id' => $request->supplier_id,
            'primary_bin_id' => $request->primary_bin_id,
            'putaway_strategy' => $request->putaway_strategy ?? 'fifo',
            'auto_reorder_enabled' => $request->auto_reorder_enabled ?? false,
            'effective_date' => $request->effective_date ?? now(),
            'expiry_date' => $request->expiry_date,
            'notes' => $request->notes,
            'is_active' => true,
        ]);

        // Create initial inventory record
        WarehouseInventory::create([
            'product_id' => $request->product_id,
            'warehouse_id' => $request->warehouse_id,
            'quantity' => 0,
            'reserved_quantity' => 0,
            'reorder_point' => $request->min_stock_level,
            'safety_stock' => $request->safety_stock,
            'bin_id' => $request->primary_bin_id,
            'cost_basis' => WarehouseInventory::COST_BASIS_FIFO,
            'lead_time_days' => $request->lead_time_days,
            'average_daily_sales' => 0,
        ]);

        return response()->json([
            'message' => 'تم حفظ الربط بنجاح',
            'data' => $assignment->load(['product', 'warehouse', 'primaryBin', 'supplier']),
        ]);
    }

    public function suggestStockLevels(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'lead_time_days' => 'nullable|integer|min:1',
        ]);

        $leadTimeDays = (int) ($request->lead_time_days ?? 7);
        $lookbackDays = 90;

        // Real consumption, not a made-up constant: everything this product
        // has actually shipped from this warehouse in the last 90 days.
        $totalConsumed = (int) StockMovement::where('product_id', $request->product_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->where('movement_type', StockMovement::TYPE_OUT)
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->sum('quantity');

        $avgDailyConsumption = $totalConsumed / $lookbackDays;
        // The safety buffer covers demand during lead time, but is capped —
        // a six-month lead time shouldn't demand six months of safety stock
        // on top of the lead-time coverage itself.
        $safetyDays = min($leadTimeDays, 7);

        $safetyStock = (int) ceil($avgDailyConsumption * $safetyDays);
        $minStock = (int) ceil($avgDailyConsumption * $leadTimeDays) + $safetyStock;
        // Headroom for roughly a month between reorders on top of the
        // reorder point itself.
        $maxStock = $minStock + (int) ceil($avgDailyConsumption * 30);

        return response()->json([
            'min_stock' => $minStock,
            'max_stock' => max($maxStock, $minStock + 1),
            'safety_stock' => $safetyStock,
            // So the screen can say what the numbers are based on instead of
            // presenting them as if they came from nowhere.
            'avg_daily_consumption' => round($avgDailyConsumption, 2),
            'based_on_days' => $lookbackDays,
            'total_consumed' => $totalConsumed,
        ]);
    }

    // ==================== Stock ====================

    public function getStockBalance(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $balance = WarehouseInventory::where('product_id', $request->product_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->with(['product', 'warehouse'])
            ->first();

        if (! $balance) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $balance->id,
                'product_id' => $balance->product_id,
                'warehouse_id' => $balance->warehouse_id,
                'quantity' => $balance->quantity,
                'reserved_quantity' => $balance->reserved_quantity,
                // `quantity - reserved` counted damaged and quarantined units as
                // available, so this screen — the one an operator checks before
                // committing a movement — offered stock that cannot leave the
                // shelf. One definition now, shared with the sell gate.
                'available_quantity' => $balance->available_stock,
                'damaged_quantity' => $balance->damaged_quantity,
                'quarantined_quantity' => $balance->quarantined_quantity,
                'reorder_point' => $balance->reorder_point,
                'safety_stock' => $balance->safety_stock,
                'product' => $balance->product,
                'warehouse' => $balance->warehouse,
            ],
        ]);
    }

    public function getStockTransactions(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        // The stock_movements table this screen was always meant to read
        // from already exists and is exactly what every other WMS write
        // (receive/issue/adjust/transfer) records to — this just never
        // queried it, so the ledger the screen renders was always empty.
        $transactions = StockMovement::where('product_id', $request->product_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->with('creator:id,name')
            ->latest('id')
            ->limit(500)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'movement_type' => $m->movement_type,
                'movement_type_text' => $m->movement_type_text,
                'quantity' => (int) $m->quantity,
                // The model column is `reference`; the screen has always
                // called it `reference_document`.
                'reference_document' => $m->reference,
                'notes' => $m->notes,
                'unit_cost' => (float) $m->unit_cost,
                'total_cost' => (float) $m->total_cost,
                'created_by_name' => $m->creator?->name,
                'created_at' => $m->created_at?->toDateTimeString(),
            ]);

        return response()->json([
            'data' => $transactions,
        ]);
    }

    public function createStockMovement(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'movement_type' => 'required|in:in,out,adjustment,transfer',
            // A transfer with no destination is indistinguishable from a
            // plain issue — the stock would leave the source warehouse and
            // never be recorded anywhere else. Required, distinct from the
            // source, and a real warehouse.
            'to_warehouse_id' => 'required_if:movement_type,transfer|nullable|exists:warehouses,id|different:warehouse_id',
            'quantity' => 'required|numeric|min:0.01',
            'reference_document' => 'nullable|string',
            'movement_key' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        // Everything goes through the single InventoryService path so the
        // movement row, the warehouse row and products.stock_quantity cannot
        // drift apart. A repeated post with the same movement_key is a no-op.
        $inventory = app(InventoryService::class);

        $options = [
            'key' => $request->movement_key,
            'reference' => $request->reference_document,
            'source' => 'wms',
            'reason' => $request->notes,
            'unit_cost' => $request->unit_cost ?? 0,
            'created_by' => auth()->id(),
        ];

        try {
            if ($request->movement_type === 'transfer') {
                // issue() alone would delete the stock from the source
                // warehouse with no record of where it went. transfer()
                // moves it: an issue out of the source and a receipt into
                // the destination, in one DB transaction.
                [$movement, $inMovement] = $inventory->transfer(
                    $request->product_id,
                    (int) $request->quantity,
                    $request->warehouse_id,
                    (int) $request->to_warehouse_id,
                    $options
                );
            } else {
                $movement = match ($request->movement_type) {
                    'in' => $inventory->receive($request->product_id, (int) $request->quantity, $request->warehouse_id, $options),
                    'adjustment' => $inventory->adjust($request->product_id, (int) $request->quantity, $request->warehouse_id, $options),
                    default => $inventory->issue($request->product_id, (int) $request->quantity, $request->warehouse_id, $options),
                };
                $inMovement = null;
            }
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $warehouseRow = WarehouseInventory::where('product_id', $request->product_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->first();

        // بث حدث الحركة
        broadcast(new StockMovementCreated($movement, $request->warehouse_id));

        $lowStockRows = [[$warehouseRow, $request->warehouse_id]];

        // A transfer also changes the destination warehouse's stock — broadcast
        // that leg and check it for a reorder breach too, not just the source.
        if ($inMovement) {
            broadcast(new StockMovementCreated($inMovement, (int) $request->to_warehouse_id));

            $destinationRow = WarehouseInventory::where('product_id', $request->product_id)
                ->where('warehouse_id', $request->to_warehouse_id)
                ->first();
            $lowStockRows[] = [$destinationRow, (int) $request->to_warehouse_id];
        }

        // إرسال تنبيه إذا وصل للحد الأدنى
        foreach ($lowStockRows as [$row, $warehouseId]) {
            if ($row && $row->quantity <= ($row->reorder_point ?? 0)) {
                broadcast(new StockAlert([
                    'product_id' => $row->product_id,
                    'warehouse_id' => $warehouseId,
                    'message' => "المنتج {$row->product->name} في المستودع وصل للحد الأدنى",
                    'created_at' => now()->format('Y-m-d H:i:s'),
                ]));
            }
        }

        return response()->json([
            'message' => 'تم إضافة الحركة بنجاح',
            'data' => $warehouseRow?->refresh(),
        ]);
    }

    // ==================== Warehouse Bins ====================

    public function indexBins(Request $request)
    {
        $query = WarehouseBin::with(['warehouse:id,name,code']);

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->zone) {
            $query->byZone($request->zone);
        }

        if ($request->type) {
            $query->byType($request->type);
        }

        if ($request->active_only) {
            $query->active();
        }

        $perPage = min((int) $request->input('per_page', 20) ?: 20, 100);
        $bins = $query->withCount('inventory')->paginate($perPage);
        $bins->getCollection()->transform(fn ($b) => $this->presentBin($b));

        return response()->json($bins);
    }

    /** One bin, flattened — the warehouse relation rendered as an object was showing "[object Object]" on the list screen. */
    private function presentBin(WarehouseBin $b): array
    {
        return [
            'id' => $b->id,
            'code' => $b->code,
            'bin_code' => $b->bin_code,
            'name' => $b->name,
            'warehouse_id' => (int) $b->warehouse_id,
            'warehouse_name' => $b->warehouse?->name,
            'zone' => $b->zone,
            'aisle' => $b->aisle,
            'shelf' => $b->shelf,
            'level' => $b->level,
            'type' => $b->type,
            'type_text' => $b->type_text,
            'capacity_type' => $b->capacity_type,
            'capacity_value' => $b->capacity_value !== null ? (float) $b->capacity_value : null,
            'current_utilization' => (float) $b->current_utilization,
            'utilization_percentage' => round($b->utilization_percentage, 1),
            'is_active' => (bool) $b->is_active,
            'requires_equipment' => (bool) $b->requires_equipment,
            'inventory_count' => (int) ($b->inventory_count ?? 0),
            'notes' => $b->notes,
        ];
    }

    public function showBin($id)
    {
        $bin = WarehouseBin::with(['warehouse:id,name,code', 'inventory.product', 'inventory.productVariant'])
            ->withCount('inventory')
            ->findOrFail($id);

        return response()->json(['data' => $this->presentBin($bin)]);
    }

    public function storeBin(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'code' => 'required|string|unique:warehouse_bins,code',
            'name' => 'required|string',
            'zone' => 'nullable|string',
            'aisle' => 'nullable|string',
            'shelf' => 'nullable|string',
            'level' => 'nullable|string',
            'type' => 'required|in:storage,picking,receiving,shipping,quarantine,returns',
            'capacity_type' => 'required|in:volume,weight,count',
            'capacity_value' => 'nullable|numeric',
            'is_active' => 'boolean',
            'requires_equipment' => 'boolean',
            'dimensions' => 'nullable|array',
            'coordinates' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        // `code` is what this screen and product assignments read; `bin_code`
        // is the original column and Picking/Packing/Cycle Count still read
        // it (see the migration that split them). Keeping both set here is
        // what keeps a bin created through this form showing its code on
        // those screens instead of a blank.
        $bin = WarehouseBin::create([...$validated, 'bin_code' => $validated['code']]);

        return response()->json(['data' => $this->presentBin($bin->load('warehouse:id,name,code'))], 201);
    }

    public function updateBin(Request $request, $id)
    {
        $bin = WarehouseBin::findOrFail($id);

        $validated = $request->validate([
            'code' => 'string|unique:warehouse_bins,code,'.$id,
            'name' => 'string',
            'zone' => 'nullable|string',
            'aisle' => 'nullable|string',
            'shelf' => 'nullable|string',
            'level' => 'nullable|string',
            'type' => 'in:storage,picking,receiving,shipping,quarantine,returns',
            'capacity_type' => 'in:volume,weight,count',
            'capacity_value' => 'nullable|numeric',
            'is_active' => 'boolean',
            'requires_equipment' => 'boolean',
            'dimensions' => 'nullable|array',
            'coordinates' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        // Keep bin_code in step with code — see storeBin().
        if (array_key_exists('code', $validated)) {
            $validated['bin_code'] = $validated['code'];
        }

        $bin->update($validated);

        return response()->json(['data' => $this->presentBin($bin->load('warehouse:id,name,code'))]);
    }

    public function destroyBin($id)
    {
        $bin = WarehouseBin::findOrFail($id);

        if ($bin->inventory()->count() > 0) {
            return response()->json(['message' => 'Cannot delete bin with inventory'], 400);
        }

        $bin->delete();

        return response()->json(['message' => 'Bin deleted successfully']);
    }

    // ==================== Picking Lists ====================

    /**
     * The warehouse's picking queue.
     *
     * Returns a flattened row per list rather than the raw model graph: the
     * screen previously bound `row.order_number` and `row.warehouse` against
     * nested `salesOrder` / `warehouse` objects, so both columns rendered blank
     * on every row.
     */
    public function indexPickingLists(Request $request)
    {
        $query = PickingList::with(['warehouse:id,name,code', 'salesOrder.customer:id,name', 'picker:id,name']);

        if ($request->filled('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('picker_id')) {
            $query->byPicker($request->picker_id);
        }

        // What a supervisor actually looks for: the work that is not done.
        if ($request->boolean('open_only')) {
            $query->whereIn('status', [PickingList::STATUS_PENDING, PickingList::STATUS_IN_PROGRESS]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('list_number', 'like', "%{$search}%")
                ->orWhereHas('salesOrder', fn ($o) => $o->where('order_number', 'like', "%{$search}%")));
        }

        $lists = $query->latest('id')->paginate(min((int) $request->input('per_page', 20) ?: 20, 100));

        return response()->json([
            'success' => true,
            'data' => [
                'lists' => collect($lists->items())->map(fn ($l) => $this->presentPickingList($l))->all(),
                'status_counts' => $this->pickingStatusCounts($request->input('warehouse_id')),
                'pagination' => [
                    'current_page' => $lists->currentPage(),
                    'last_page' => $lists->lastPage(),
                    'per_page' => $lists->perPage(),
                    'total' => $lists->total(),
                    'has_more_pages' => $lists->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Every picking action answers with the refreshed list.
     *
     * The screen used to fire a second request after each pick to find out what
     * changed, which left a window where the progress bar and the buttons
     * disagreed with the server about the state of the list.
     */
    private function pickingResponse(PickingList $list, string $message)
    {
        $list->refresh()->load([
            'warehouse:id,name,code', 'salesOrder.customer:id,name,phone',
            'picker:id,name', 'items.product:id,sku,name_ar,name_en,barcode', 'items.bin',
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['list' => $this->presentPickingList($list, withItems: true)],
        ]);
    }

    /** How many lists sit in each state, across the whole queue not the page. */
    private function pickingStatusCounts($warehouseId = null): array
    {
        $counts = PickingList::query()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'all' => (int) $counts->sum(),
            'pending' => (int) ($counts[PickingList::STATUS_PENDING] ?? 0),
            'in_progress' => (int) ($counts[PickingList::STATUS_IN_PROGRESS] ?? 0),
            'completed' => (int) ($counts[PickingList::STATUS_COMPLETED] ?? 0),
            'cancelled' => (int) ($counts[PickingList::STATUS_CANCELLED] ?? 0),
        ];
    }

    /**
     * One picking list, flattened for the screen.
     *
     * Labels and progress are computed here so every client shows the same
     * wording and the same percentage for the same row.
     */
    private function presentPickingList(PickingList $l, bool $withItems = false): array
    {
        $total = (int) $l->total_items;
        $picked = (int) $l->picked_items;

        $row = [
            'id' => $l->id,
            'list_number' => $l->list_number,
            'status' => $l->status,
            'status_text' => $l->status_text,
            'priority' => $l->priority,
            'priority_text' => $l->priority_text,
            'warehouse_id' => (int) $l->warehouse_id,
            'warehouse_name' => $l->warehouse?->name,
            'sales_order_id' => $l->sales_order_id ? (int) $l->sales_order_id : null,
            'order_number' => $l->salesOrder?->order_number,
            'customer_name' => $l->salesOrder?->customer?->name,
            'picker_id' => $l->picker_id ? (int) $l->picker_id : null,
            'picker_name' => $l->picker?->name,
            'total_items' => $total,
            'picked_items' => $picked,
            'progress' => $total > 0 ? (int) round($picked / $total * 100) : 0,
            // The screen shows or hides its buttons off these rather than
            // re-deriving the rules, so the two cannot disagree.
            'can_start' => $l->status === PickingList::STATUS_PENDING,
            'can_complete' => $l->status === PickingList::STATUS_IN_PROGRESS,
            'can_cancel' => ! in_array($l->status, [PickingList::STATUS_COMPLETED, PickingList::STATUS_CANCELLED], true),
            'started_at' => $l->started_at?->toDateTimeString(),
            'completed_at' => $l->completed_at?->toDateTimeString(),
            'created_at' => $l->created_at?->toDateTimeString(),
        ];

        if ($withItems) {
            $row['items'] = $l->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => (int) $i->product_id,
                'sku' => $i->product?->sku,
                'product_name' => $i->product?->name_ar ?? $i->product?->name_en,
                'barcode' => $i->barcode,
                'bin_code' => $i->bin?->bin_code,
                'bin_location' => $i->bin ? trim(($i->bin->zone ?? '').' '.($i->bin->rack ?? '').' '.($i->bin->shelf ?? '')) : null,
                'quantity_to_pick' => (int) $i->quantity_to_pick,
                'quantity_picked' => (int) $i->quantity_picked,
                'remaining' => max(0, (int) $i->quantity_to_pick - (int) $i->quantity_picked),
                'status' => $i->status,
                'status_text' => match ($i->status) {
                    PickingListItem::STATUS_PENDING => 'بانتظار السحب',
                    PickingListItem::STATUS_PICKED => 'مسحوب',
                    PickingListItem::STATUS_SHORT => 'ناقص',
                    PickingListItem::STATUS_CANCELLED => 'ملغي',
                    default => $i->status,
                },
                'verified' => (bool) $i->verified,
                'picked_at' => $i->picked_at?->toDateTimeString(),
                'sort_order' => (int) $i->sort_order,
            ])->sortBy('sort_order')->values();
        }

        return $row;
    }

    /** One picking list with its lines — the picker's working screen. */
    public function showPickingList($id)
    {
        $list = PickingList::with([
            'warehouse:id,name,code', 'salesOrder.customer:id,name,phone',
            'picker:id,name', 'items.product:id,sku,name_ar,name_en,barcode', 'items.bin',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => ['list' => $this->presentPickingList($list, withItems: true)],
        ]);
    }

    public function createPickingList(Request $request)
    {
        $validated = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $order = SalesOrder::findOrFail($validated['sales_order_id']);

        try {
            $pickingList = $this->pickingService->createPickingList($order, $validated['warehouse_id'] ?? null);
            $pickingList->load(['warehouse', 'salesOrder', 'items.product', 'items.bin']);

            return response()->json($pickingList, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function startPicking(Request $request, $id)
    {
        $list = PickingList::findOrFail($id);

        try {
            $this->pickingService->startPicking($list, $request->user()->id);

            return $this->pickingResponse($list, 'بدأ سحب الأصناف من الرفوف.');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function pickItem(Request $request, $itemId)
    {
        $item = PickingListItem::findOrFail($itemId);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'verified' => 'boolean',
        ]);

        try {
            $this->pickingService->pickItem($item, $validated['quantity'], $validated['verified'] ?? false);

            // The whole list comes back so the screen never has to guess at the
            // new progress or re-request it.
            return $this->pickingResponse($item->pickingList()->first(), 'تم تسجيل السحب.');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function completePicking($id)
    {
        $list = PickingList::findOrFail($id);

        try {
            $this->pickingService->completePicking($list);

            return $this->pickingResponse($list, 'اكتمل التجهيز — الأصناف جاهزة للشحن.');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancelPicking($id)
    {
        $list = PickingList::findOrFail($id);

        try {
            $this->pickingService->cancelPicking($list);

            return $this->pickingResponse($list, 'أُلغيت قائمة التجهيز.');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function getPickingStatistics(Request $request)
    {
        $stats = $this->pickingService->getPickingStatistics(
            $request->warehouse_id,
            $request->from_date ? now()->parse($request->from_date) : null,
            $request->to_date ? now()->parse($request->to_date) : null
        );

        return response()->json($stats);
    }

    // ==================== Packing Lists ====================

    public function indexPackingLists(Request $request)
    {
        $query = PackingList::with(['warehouse:id,name,code', 'pickingList:id,list_number,status', 'salesOrder.customer:id,name', 'packer:id,name']);

        if ($request->filled('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('packer_id')) {
            $query->byPacker($request->packer_id);
        }

        $lists = $query->latest('id')->paginate(min((int) $request->input('per_page', 20) ?: 20, 100));

        return response()->json([
            'success' => true,
            'data' => [
                'lists' => collect($lists->items())->map(fn ($l) => $this->presentPackingList($l))->all(),
                'pagination' => [
                    'current_page' => $lists->currentPage(),
                    'last_page' => $lists->lastPage(),
                    'per_page' => $lists->perPage(),
                    'total' => $lists->total(),
                    'has_more_pages' => $lists->hasMorePages(),
                ],
            ],
        ]);
    }

    /** Every packing action answers with the refreshed list — same reasoning as picking. */
    private function packingResponse(PackingList $list, string $message)
    {
        $list->refresh()->load(['warehouse:id,name,code', 'pickingList:id,list_number,status', 'salesOrder.customer:id,name', 'packer:id,name', 'items.product:id,sku,name_ar,name_en']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['list' => $this->presentPackingList($list, withItems: true)],
        ]);
    }

    /** One packing list, flattened for the screen — mirrors presentPickingList. */
    private function presentPackingList(PackingList $l, bool $withItems = false): array
    {
        $row = [
            'id' => $l->id,
            'list_number' => $l->list_number,
            'status' => $l->status,
            'status_text' => $l->status_text,
            'warehouse_id' => (int) $l->warehouse_id,
            'warehouse_name' => $l->warehouse?->name,
            'picking_list_id' => $l->picking_list_id ? (int) $l->picking_list_id : null,
            'picking_list_number' => $l->pickingList?->list_number,
            'sales_order_id' => $l->sales_order_id ? (int) $l->sales_order_id : null,
            'order_number' => $l->salesOrder?->order_number,
            'customer_name' => $l->salesOrder?->customer?->name,
            'packer_id' => $l->packer_id ? (int) $l->packer_id : null,
            'packer_name' => $l->packer?->name,
            'total_packages' => (int) $l->total_packages,
            'total_weight' => (float) $l->total_weight,
            'box_type' => $l->box_type,
            'packing_instructions' => $l->packing_instructions,
            'notes' => $l->notes,
            // The screen shows or hides its buttons off these rather than
            // re-deriving the rules, so the two cannot disagree.
            'can_start' => $l->canStart(),
            'can_complete' => $l->status === PackingList::STATUS_IN_PROGRESS,
            'can_cancel' => $l->status !== PackingList::STATUS_COMPLETED && $l->status !== PackingList::STATUS_CANCELLED,
            'started_at' => $l->started_at?->toDateTimeString(),
            'completed_at' => $l->completed_at?->toDateTimeString(),
            'created_at' => $l->created_at?->toDateTimeString(),
        ];

        if ($withItems) {
            $row['items'] = $l->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => (int) $i->product_id,
                'sku' => $i->product?->sku,
                'product_name' => $i->product?->name_ar ?? $i->product?->name_en,
                'quantity' => (int) $i->quantity,
                'package_number' => $i->package_number,
                'dimensions' => $i->dimensions,
                'weight' => $i->weight !== null ? (float) $i->weight : null,
                'fragile' => (bool) $i->fragile,
                'notes' => $i->notes,
            ])->values();
        }

        return $row;
    }

    public function showPackingList($id)
    {
        $list = PackingList::with(['warehouse:id,name,code', 'pickingList:id,list_number,status', 'salesOrder.customer:id,name', 'packer:id,name', 'items.product:id,sku,name_ar,name_en'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => ['list' => $this->presentPackingList($list, withItems: true)],
        ]);
    }

    public function createPackingList(Request $request)
    {
        $validated = $request->validate([
            'picking_list_id' => 'required|exists:picking_lists,id',
        ]);

        $pickingList = PickingList::findOrFail($validated['picking_list_id']);

        try {
            $packingList = $this->packingService->createPackingList($pickingList);

            return $this->packingResponse($packingList, 'أُنشئت قائمة التعبئة.')->setStatusCode(201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function startPacking(Request $request, $id)
    {
        $list = PackingList::findOrFail($id);

        try {
            $this->packingService->startPacking($list, $request->user()->id);

            return $this->packingResponse($list, 'بدأت عملية التعبئة.');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updatePackageDetails(Request $request, $itemId)
    {
        $item = PackingListItem::findOrFail($itemId);

        $validated = $request->validate([
            'package_number' => 'nullable|string',
            'dimensions' => 'nullable|array',
            'weight' => 'nullable|numeric|min:0',
            'fragile' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->packingService->updatePackageDetails($item, $validated);

            return $this->packingResponse($item->packingList()->first(), 'تم تحديث بيانات الطرد.');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function completePacking($id)
    {
        $list = PackingList::findOrFail($id);

        try {
            $this->packingService->completePacking($list);

            return $this->packingResponse($list, 'اكتملت التعبئة — الطرود جاهزة للشحن.');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancelPacking($id)
    {
        $list = PackingList::findOrFail($id);

        try {
            $this->packingService->cancelPacking($list);

            return $this->packingResponse($list, 'أُلغيت قائمة التعبئة.');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function getPackingLabels($id)
    {
        $list = PackingList::findOrFail($id);
        $labels = $this->packingService->generatePackingLabels($list);

        return response()->json($labels);
    }

    public function validatePacking($id)
    {
        $list = PackingList::findOrFail($id);
        $validation = $this->packingService->validatePacking($list);

        return response()->json($validation);
    }

    public function getPackingStatistics(Request $request)
    {
        $stats = $this->packingService->getPackingStatistics(
            $request->warehouse_id,
            $request->from_date ? now()->parse($request->from_date) : null,
            $request->to_date ? now()->parse($request->to_date) : null
        );

        return response()->json($stats);
    }

    // ==================== Shipping Manifests ====================

    public function indexShippingManifests(Request $request)
    {
        $query = ShippingManifest::with(['warehouse', 'carrier', 'driver', 'items.salesOrder']);

        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->carrier_id) {
            $query->byCarrier($request->carrier_id);
        }

        return response()->json($query->paginate(20));
    }

    public function showShippingManifest($id)
    {
        $manifest = ShippingManifest::with(['warehouse', 'carrier', 'driver', 'items.salesOrder', 'items.packingList'])
            ->findOrFail($id);

        return response()->json($manifest);
    }

    public function createShippingManifest(Request $request)
    {
        $validated = $request->validate([
            'packing_list_ids' => 'required|array',
            'packing_list_ids.*' => 'exists:packing_lists,id',
            'carrier_id' => 'nullable|exists:carriers,id',
            'carrier_name' => 'nullable|string',
        ]);

        try {
            $manifest = $this->packingService->createShippingManifest(
                $validated['packing_list_ids'],
                $validated['carrier_id'] ?? null,
                $validated['carrier_name'] ?? null
            );

            return response()->json($manifest, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function dispatchManifest($id)
    {
        $manifest = ShippingManifest::findOrFail($id);

        try {
            $this->packingService->dispatchManifest($manifest);

            return response()->json(['message' => 'Manifest dispatched']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function markItemDelivered(Request $request, $itemId)
    {
        $item = ShippingManifestItem::findOrFail($itemId);

        try {
            $this->packingService->markItemDelivered($item, $request->signature ?? null);

            return response()->json(['message' => 'Item marked as delivered']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // ==================== Cycle Counts ====================

    public function indexCycleCounts(Request $request)
    {
        $query = CycleCount::with(['warehouse:id,name,code', 'bin:id,bin_code', 'counter:id,name', 'reviewer:id,name']);

        if ($request->filled('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->filled('bin_id')) {
            $query->byBin($request->bin_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        $counts = $query->latest('id')->paginate(min((int) $request->input('per_page', 20) ?: 20, 100));

        return response()->json([
            'success' => true,
            'data' => [
                'counts' => collect($counts->items())->map(fn ($c) => $this->presentCycleCount($c))->all(),
                'pagination' => [
                    'current_page' => $counts->currentPage(),
                    'last_page' => $counts->lastPage(),
                    'per_page' => $counts->perPage(),
                    'total' => $counts->total(),
                    'has_more_pages' => $counts->hasMorePages(),
                ],
            ],
        ]);
    }

    /** Every cycle count action answers with the refreshed count — same reasoning as picking. */
    private function cycleCountResponse(CycleCount $count, string $message)
    {
        $count->refresh()->load(['warehouse:id,name,code', 'bin:id,bin_code', 'counter:id,name', 'reviewer:id,name', 'items.product:id,sku,name_ar,name_en', 'items.bin:id,bin_code']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['count' => $this->presentCycleCount($count, withItems: true)],
        ]);
    }

    /** One cycle count, flattened for the screen — mirrors presentPickingList. */
    private function presentCycleCount(CycleCount $c, bool $withItems = false): array
    {
        $total = $c->items()->count() ?: null;
        // Same variance figures the record already carries, but expressed the
        // way an operator reads them: what fraction of counted lines matched.
        $accuracy = $total && $c->total_items
            ? (int) round((1 - ($c->variance_items / $c->total_items)) * 100)
            : null;

        $row = [
            'id' => $c->id,
            'count_number' => $c->count_number,
            'type' => $c->type,
            'type_text' => $c->type_text,
            'status' => $c->status,
            'status_text' => $c->status_text,
            'warehouse_id' => (int) $c->warehouse_id,
            'warehouse_name' => $c->warehouse?->name,
            'bin_id' => $c->bin_id ? (int) $c->bin_id : null,
            'bin_code' => $c->bin?->bin_code,
            'counter_id' => $c->counter_id ? (int) $c->counter_id : null,
            'counter_name' => $c->counter?->name,
            'reviewer_id' => $c->reviewer_id ? (int) $c->reviewer_id : null,
            'reviewer_name' => $c->reviewer?->name,
            'total_items' => (int) $c->total_items,
            'variance_items' => (int) $c->variance_items,
            'variance_value' => (float) $c->variance_value,
            'accuracy' => $accuracy,
            'requires_adjustment' => (bool) $c->requires_adjustment,
            'notes' => $c->notes,
            // The screen shows or hides its buttons off these rather than
            // re-deriving the rules, so the two cannot disagree.
            'can_start' => $c->canStart(),
            'can_add_items' => $c->status === CycleCount::STATUS_IN_PROGRESS,
            'can_complete' => $c->status === CycleCount::STATUS_IN_PROGRESS,
            'can_review' => $c->status === CycleCount::STATUS_COMPLETED && ! $c->reviewer_id,
            'can_apply_adjustment' => $c->requires_adjustment && ! $c->adjustment_by,
            'can_cancel' => ! in_array($c->status, [CycleCount::STATUS_COMPLETED, CycleCount::STATUS_CANCELLED], true),
            'started_at' => $c->started_at?->toDateTimeString(),
            'completed_at' => $c->completed_at?->toDateTimeString(),
            'reviewed_at' => $c->reviewed_at?->toDateTimeString(),
            'adjusted_at' => $c->adjusted_at?->toDateTimeString(),
            'created_at' => $c->created_at?->toDateTimeString(),
        ];

        if ($withItems) {
            $row['items'] = $c->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => (int) $i->product_id,
                'sku' => $i->product?->sku,
                'product_name' => $i->product?->name_ar ?? $i->product?->name_en,
                'bin_code' => $i->bin?->bin_code,
                'expected_quantity' => (int) $i->expected_quantity,
                'counted_quantity' => (int) $i->counted_quantity,
                'variance' => (int) $i->variance,
                'variance_value' => (float) $i->variance_value,
                'variance_reason' => $i->variance_reason,
                'variance_reason_text' => $i->variance_reason_text,
                'verified' => (bool) $i->verified,
                'notes' => $i->notes,
            ])->values();
        }

        return $row;
    }

    public function showCycleCount($id)
    {
        $count = CycleCount::with(['warehouse:id,name,code', 'bin:id,bin_code', 'counter:id,name', 'reviewer:id,name', 'items.product:id,sku,name_ar,name_en', 'items.bin:id,bin_code'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => ['count' => $this->presentCycleCount($count, withItems: true)],
        ]);
    }

    public function storeCycleCount(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'bin_id' => 'nullable|exists:warehouse_bins,id',
            'type' => 'required|in:full,partial,abc,blind',
            'notes' => 'nullable|string',
        ]);

        $count = CycleCount::create([
            'warehouse_id' => $validated['warehouse_id'],
            'bin_id' => $validated['bin_id'] ?? null,
            'count_number' => 'CC-'.str_pad(CycleCount::count() + 1, 6, '0', STR_PAD_LEFT),
            'type' => $validated['type'],
            'status' => CycleCount::STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->cycleCountResponse($count, 'أُنشئ الجرد الدوري.')->setStatusCode(201);
    }

    public function startCycleCount(Request $request, $id)
    {
        $count = CycleCount::findOrFail($id);

        if (! $count->canStart()) {
            return response()->json(['success' => false, 'message' => 'لا يمكن بدء هذا الجرد.'], 422);
        }

        $count->start($request->user()->id);

        return $this->cycleCountResponse($count, 'بدأ الجرد الدوري.');
    }

    public function addCycleCountItem(Request $request, $countId)
    {
        $count = CycleCount::findOrFail($countId);

        if ($count->status !== CycleCount::STATUS_IN_PROGRESS) {
            return response()->json(['success' => false, 'message' => 'يجب بدء الجرد قبل إضافة أصناف إليه.'], 422);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'bin_id' => 'nullable|exists:warehouse_bins,id',
            'counted_quantity' => 'required|integer|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'variance_reason' => 'nullable|in:theft,damage,data_entry,unknown',
            'notes' => 'nullable|string',
        ]);

        // The "expected" figure is what makes a count meaningful — it has to
        // come from the system's own stock record, not from whatever the
        // request claims, or a careless (or dishonest) entry could report
        // zero variance on real shrinkage. It's read straight off
        // warehouse_inventory for this product/warehouse/bin combination.
        $expectedQuantity = (int) WarehouseInventory::where('product_id', $validated['product_id'])
            ->where('warehouse_id', $count->warehouse_id)
            ->when($validated['product_variant_id'] ?? null, fn ($q, $v) => $q->where('product_variant_id', $v))
            ->when($validated['bin_id'] ?? null, fn ($q, $b) => $q->where('bin_id', $b))
            ->value('quantity') ?? 0;

        $product = Product::find($validated['product_id']);

        $item = $count->items()->create([
            ...$validated,
            'expected_quantity' => $expectedQuantity,
            'unit_cost' => $validated['unit_cost'] ?? $product?->cost_price ?? 0,
        ]);
        $item->calculateVariance();
        $count->calculateVariance();

        return $this->cycleCountResponse($count, 'أُضيف الصنف إلى الجرد.');
    }

    public function completeCycleCount($id)
    {
        $count = CycleCount::findOrFail($id);

        if ($count->status !== CycleCount::STATUS_IN_PROGRESS) {
            return response()->json(['success' => false, 'message' => 'الجرد ليس قيد التنفيذ.'], 422);
        }

        if ($count->items()->count() === 0) {
            return response()->json(['success' => false, 'message' => 'لا يمكن إكمال جرد لم تُضف إليه أي أصناف.'], 422);
        }

        DB::transaction(function () use ($count) {
            $count->complete();
            $count->calculateVariance();
        });

        return $this->cycleCountResponse($count, 'اكتمل الجرد الدوري.');
    }

    public function reviewCycleCount(Request $request, $id)
    {
        $count = CycleCount::findOrFail($id);

        if ($count->status !== CycleCount::STATUS_COMPLETED) {
            return response()->json(['success' => false, 'message' => 'يجب إكمال الجرد قبل مراجعته.'], 422);
        }

        $count->review($request->user()->id);

        return $this->cycleCountResponse($count, 'رُوجع الجرد الدوري.');
    }

    public function applyAdjustment(Request $request, $id)
    {
        $count = CycleCount::findOrFail($id);

        if (! $count->requires_adjustment || $count->adjustment_by) {
            return response()->json(['success' => false, 'message' => 'لا توجد تسوية مطلوبة لهذا الجرد.'], 422);
        }

        $count->applyAdjustment($request->user()->id);

        return $this->cycleCountResponse($count, 'طُبّقت التسوية على المخزون.');
    }

    public function cancelCycleCount($id)
    {
        $count = CycleCount::findOrFail($id);

        if (in_array($count->status, [CycleCount::STATUS_COMPLETED, CycleCount::STATUS_CANCELLED], true)) {
            return response()->json(['success' => false, 'message' => 'لا يمكن إلغاء جرد مكتمل أو ملغى بالفعل.'], 422);
        }

        $count->cancel();

        return $this->cycleCountResponse($count, 'أُلغي الجرد الدوري.');
    }

    // ==================== Warehouses CRUD ====================

    public function indexWarehouses(Request $request)
    {
        $query = Warehouse::withSum('inventory as total_stock', 'quantity');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('code', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('location_type')) {
            $query->where('location_type', $request->location_type);
        }

        $warehouses = $query->with('manager:id,name,email')->latest()->paginate($request->input('per_page', 20));

        $warehouses->getCollection()->transform(function ($warehouse) {
            $warehouse->utilization_percentage = $warehouse->capacity > 0
                ? round((($warehouse->total_stock ?? 0) / $warehouse->capacity) * 100, 1)
                : null;

            return $warehouse;
        });

        return response()->json($warehouses);
    }

    public function showWarehouse($id)
    {
        $warehouse = Warehouse::with('manager:id,name,email')->findOrFail($id);

        return response()->json($warehouse);
    }

    /** Lists users who can be linked as a warehouse's `manager_id`. */
    public function indexManagers()
    {
        return response()->json(
            User::select('id', 'name', 'email')->orderBy('name')->get()
        );
    }

    public function storeWarehouse(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'manager_name' => 'nullable|string|max:255',
            'manager_phone' => 'nullable|string|max:255',
            'location_type' => 'required|in:warehouse,branch,distribution_center,3pl',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'capacity' => 'nullable|integer',
            'operating_hours' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'is_primary' => 'sometimes|boolean',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $warehouse = DB::transaction(function () use ($validated) {
            $isPrimary = $validated['is_primary'] ?? false;

            if ($isPrimary) {
                // Only one warehouse is ever primary — several routing/sourcing
                // services treat it as singular already, nothing enforced it.
                Warehouse::where('is_primary', true)->update(['is_primary' => false]);
            }

            return Warehouse::create(array_merge($validated, [
                'is_active' => $validated['is_active'] ?? true,
                'is_primary' => $isPrimary,
            ]));
        });

        return response()->json($warehouse, 201);
    }

    public function updateWarehouse(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|max:50|unique:warehouses,code,'.$id,
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'manager_name' => 'nullable|string|max:255',
            'manager_phone' => 'nullable|string|max:255',
            'location_type' => 'in:warehouse,branch,distribution_center,3pl',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'capacity' => 'nullable|integer',
            'operating_hours' => 'nullable|array',
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        DB::transaction(function () use ($warehouse, $validated) {
            if (($validated['is_primary'] ?? false) === true) {
                Warehouse::where('is_primary', true)
                    ->where('id', '!=', $warehouse->id)
                    ->update(['is_primary' => false]);
            }

            $warehouse->update($validated);
        });

        return response()->json($warehouse->fresh());
    }

    public function destroyWarehouse($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        // warehouse_inventory and warehouse_bins both cascade-delete with the
        // warehouse — without this guard, deleting one silently wipes every
        // stock record it holds (bin-level inventory is a subset of this).
        $hasStock = $warehouse->inventory()->where('quantity', '>', 0)->exists();

        if ($hasStock) {
            return response()->json([
                'message' => 'لا يمكن حذف المستودع لأنه يحتوي على مخزون فعلي. رجاءً انقل أو صفّر الرصيد أولاً.',
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المستودع بنجاح',
        ]);
    }

    public function getWmsStats()
    {
        return response()->json([
            'warehouses' => Warehouse::count(),
            'bins' => WarehouseBin::count(),
            'pickingLists' => PickingList::count(),
            'packingLists' => PackingList::count(),
            'pickingPending' => PickingList::where('status', 'pending')->count(),
            'packingPending' => PackingList::where('status', 'pending')->count(),
            'cycleCounts' => CycleCount::count(),
        ]);
    }

    // ==================== Performance ====================

    /**
     * Snapshot KPIs for the selected window (default: the last 30 days),
     * built entirely from real picking/packing/cycle-count activity.
     *
     * "Packing accuracy" is deliberately absent: packing items are copied
     * 1:1 from what was picked, so there is nothing in the data model today
     * to compare a "packed correctly" count against. Inventing one would be
     * exactly the kind of number this endpoint replaces.
     */
    public function getPerformanceMetrics(Request $request)
    {
        $from = $request->from_date ? now()->parse($request->from_date) : now()->subDays(30);
        $to = $request->to_date ? now()->parse($request->to_date) : now();
        $warehouseId = $request->warehouse_id;

        $picking = $this->pickingService->getPickingStatistics($warehouseId, $from, $to);
        $packing = $this->packingService->getPackingStatistics($warehouseId, $from, $to);
        $cycleCounts = $this->cycleCountStatistics($warehouseId, $from, $to);

        return response()->json([
            'picking_accuracy' => $picking['picking_accuracy'],
            'cycle_count_accuracy' => $cycleCounts['accuracy'],
            'average_picking_time' => round($picking['average_completion_time'], 1),
            'average_packing_time' => round($packing['average_completion_time'], 1),
            // A real count for the period, not a rate — labelled as such on
            // the screen rather than implying a per-day figure this does not
            // actually compute.
            'total_units_picked' => (int) $picking['total_items_picked'],
            'completed_picking_lists' => $picking['completed_lists'],
            'completed_packing_lists' => $packing['completed_lists'],
            'completed_cycle_counts' => $cycleCounts['completed_counts'],
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
        ]);
    }

    /**
     * Monthly accuracy history for the trend chart.
     *
     * Grouped in PHP over a fetched Collection rather than SQL-side
     * YEAR()/MONTH() (used elsewhere in the codebase but MySQL-only) — this
     * module's own statistics methods already work this way, and the volume
     * involved (a few months of picking/cycle-count activity) is small
     * enough that portability costs nothing here.
     */
    public function getPerformanceTrends(Request $request)
    {
        $months = max(1, min((int) $request->input('months', 6), 24));
        $warehouseId = $request->warehouse_id;
        $from = now()->startOfMonth()->subMonths($months - 1);

        $pickingLists = PickingList::query()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->where('created_at', '>=', $from)
            ->get();

        $cycleCounts = CycleCount::query()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->where('created_at', '>=', $from)
            ->get();

        $labels = [];
        $pickingAccuracy = [];
        $cycleCountAccuracy = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $key = $month->format('Y-m');
            $labels[] = $key;

            $monthPicking = $pickingLists->filter(fn ($l) => $l->created_at->format('Y-m') === $key)->values();
            $monthCounts = $cycleCounts->filter(fn ($c) => $c->created_at->format('Y-m') === $key)->values();

            $pickingAccuracy[] = $this->pickingService->calculatePickingAccuracy($monthPicking);
            $cycleCountAccuracy[] = $this->cycleCountAccuracyFor($monthCounts);
        }

        return response()->json([
            'labels' => $labels,
            'picking_accuracy' => $pickingAccuracy,
            'cycle_count_accuracy' => $cycleCountAccuracy,
        ]);
    }

    private function cycleCountStatistics($warehouseId, $fromDate, $toDate): array
    {
        $query = CycleCount::query();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $counts = $query->get();

        return [
            'total_counts' => $counts->count(),
            'completed_counts' => $counts->where('status', CycleCount::STATUS_COMPLETED)->count(),
            'accuracy' => $this->cycleCountAccuracyFor($counts),
        ];
    }

    /** Null (not 0 or 100) when nothing completed in the period backs a number. */
    private function cycleCountAccuracyFor($counts): ?float
    {
        $completed = $counts->where('status', CycleCount::STATUS_COMPLETED);
        $totalItems = $completed->sum('total_items');

        if ($totalItems <= 0) {
            return null;
        }

        return round((1 - $completed->sum('variance_items') / $totalItems) * 100, 1);
    }
}
