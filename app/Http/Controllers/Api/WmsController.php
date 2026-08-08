<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductWarehouseAssignment;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseBin;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\PackingList;
use App\Models\PackingListItem;
use App\Services\PickingService;
use App\Services\PackingService;
use App\Services\Inventory\InventoryService;
use App\Events\StockMovementCreated;
use App\Events\StockAlert;
use Illuminate\Http\Request;
use App\Models\CycleCount;
use App\Models\CycleCountItem;
use App\Models\SalesOrder;
use App\Models\ShippingManifest;
use App\Models\ShippingManifestItem;
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
            'reorder_products' => WarehouseInventory::whereColumn('available_quantity', '<=', 'reorder_point')->count(),
            'total_stock' => WarehouseInventory::sum('quantity'),
            'total_value' => WarehouseInventory::selectRaw('SUM(quantity * cost_basis) as total')->value('total') ?? 0,
            'today_movements' => 0, // TODO: Add movements table
            'active_users' => 0, // TODO: Add user activity tracking
        ];

        $topProducts = Product::withSum('inventory as total_consumption', 'quantity')
            ->orderByDesc('total_consumption')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'consumption' => $product->total_consumption ?? 0,
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

        $alerts = WarehouseInventory::whereColumn('available_quantity', '<=', 'reorder_point')
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

        return response()->json([
            'stats' => $stats,
            'top_products' => $topProducts,
            'warehouse_distribution' => $warehouseDistribution,
            'alerts' => $alerts,
        ]);
    }

    // ==================== Products ====================

    public function indexProducts(Request $request)
    {
        $query = Product::with(['category', 'inventory.warehouse']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->category) {
            $query->where('category_id', $request->category);
        }

        $products = $query->get()->map(function ($product) {
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

        return response()->json([
            'data' => $products,
        ]);
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
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
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
                'available_quantity' => $inventory ? $inventory->available_quantity : 0,
                'reserved_quantity' => $inventory ? $inventory->reserved_quantity : 0,
                'min_stock_level' => $assignment->min_stock_level,
                'max_stock_level' => $assignment->max_stock_level,
                'safety_stock' => $assignment->safety_stock,
                'cost_price' => $inventory ? $inventory->cost_basis : $product->cost_price,
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
                'available_quantity' => $inventory ? $inventory->available_quantity : 0,
                'reserved_quantity' => $inventory ? $inventory->reserved_quantity : 0,
                'damaged_quantity' => $inventory ? $inventory->damaged_quantity : 0,
                'quarantined_quantity' => $inventory ? $inventory->quarantined_quantity : 0,
                'min_stock_level' => $assignment->min_stock_level,
                'max_stock_level' => $assignment->max_stock_level,
                'safety_stock' => $assignment->safety_stock,
                'cost_price' => $inventory ? $inventory->cost_basis : $product->cost_price,
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
                    'key' => 'assign:' . $assignment->id,
                    'source' => 'assignment',
                    'reference' => $assignment->id,
                    'reason' => 'تحديث رصيد التعيين من شاشة المخزون',
                    'allow_negative' => true,
                    'created_by' => auth()->id(),
                ]);

                $warehouseRow->refresh();
            }

            if ($warehouseRow && $request->filled('cost_price')) {
                $warehouseRow->cost_basis = $request->cost_price;
                $warehouseRow->save();
            }

            if (!$warehouseRow && $newQuantity > 0) {
                $inventory->receive($assignment->product_id, $newQuantity, $assignment->warehouse_id, [
                    'key' => 'assign:' . $assignment->id . ':opening',
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
        ]);

        // حساب متوسط الاستهلاك الشهري (محاكاة)
        // في الواقع، يجب جلب البيانات من جدول الحركات
        $avgConsumption = 10; // قيمة افتراضية

        return response()->json([
            'min_stock' => $avgConsumption * 3,  // 3 أشهر
            'max_stock' => $avgConsumption * 6,  // 6 أشهر
            'safety_stock' => $avgConsumption * 1.5,
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

        if (!$balance) {
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
                'available_quantity' => $balance->quantity - $balance->reserved_quantity,
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

        // TODO: Add stock movements table and query here
        $transactions = collect();

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
            $movement = match ($request->movement_type) {
                'in' => $inventory->receive($request->product_id, (int) $request->quantity, $request->warehouse_id, $options),
                'transfer' => $inventory->issue($request->product_id, (int) $request->quantity, $request->warehouse_id, $options),
                'adjustment' => $inventory->adjust($request->product_id, (int) $request->quantity, $request->warehouse_id, $options),
                default => $inventory->issue($request->product_id, (int) $request->quantity, $request->warehouse_id, $options),
            };
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

        // إرسال تنبيه إذا وصل للحد الأدنى
        if ($warehouseRow && $warehouseRow->quantity <= ($warehouseRow->reorder_point ?? 0)) {
            broadcast(new StockAlert([
                'product_id' => $warehouseRow->product_id,
                'warehouse_id' => $request->warehouse_id,
                'message' => "المنتج {$warehouseRow->product->name} في المستودع وصل للحد الأدنى",
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]));
        }

        return response()->json([
            'message' => 'تم إضافة الحركة بنجاح',
            'data' => $warehouseRow?->refresh(),
        ]);
    }

    // ==================== Warehouse Bins ====================

    public function indexBins(Request $request)
    {
        $query = WarehouseBin::with(['warehouse', 'inventory.product']);

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

        return response()->json($query->paginate(20));
    }

    public function showBin($id)
    {
        $bin = WarehouseBin::with(['warehouse', 'inventory.product', 'inventory.productVariant'])
            ->findOrFail($id);

        return response()->json($bin);
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

        $bin = WarehouseBin::create($validated);

        return response()->json($bin, 201);
    }

    public function updateBin(Request $request, $id)
    {
        $bin = WarehouseBin::findOrFail($id);

        $validated = $request->validate([
            'code' => 'string|unique:warehouse_bins,code,' . $id,
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

        $bin->update($validated);

        return response()->json($bin);
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

    public function indexPickingLists(Request $request)
    {
        $query = PickingList::with(['warehouse', 'salesOrder.customer', 'picker', 'items.product', 'items.bin']);

        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->picker_id) {
            $query->byPicker($request->picker_id);
        }

        return response()->json($query->paginate(20));
    }

    public function showPickingList($id)
    {
        $list = PickingList::with(['warehouse', 'salesOrder.customer', 'picker', 'items.product', 'items.bin', 'items.productVariant'])
            ->findOrFail($id);

        return response()->json($list);
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
            return response()->json(['message' => 'Picking started']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
            return response()->json(['message' => 'Item picked successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function completePicking($id)
    {
        $list = PickingList::findOrFail($id);

        try {
            $this->pickingService->completePicking($list);
            return response()->json(['message' => 'Picking completed']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function cancelPicking($id)
    {
        $list = PickingList::findOrFail($id);

        try {
            $this->pickingService->cancelPicking($list);
            return response()->json(['message' => 'Picking cancelled']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
        $query = PackingList::with(['warehouse', 'pickingList', 'salesOrder.customer', 'packer', 'items.product']);

        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->packer_id) {
            $query->byPacker($request->packer_id);
        }

        return response()->json($query->paginate(20));
    }

    public function showPackingList($id)
    {
        $list = PackingList::with(['warehouse', 'pickingList', 'salesOrder.customer', 'packer', 'items.product', 'items.productVariant'])
            ->findOrFail($id);

        return response()->json($list);
    }

    public function createPackingList(Request $request)
    {
        $validated = $request->validate([
            'picking_list_id' => 'required|exists:picking_lists,id',
        ]);

        $pickingList = PickingList::findOrFail($validated['picking_list_id']);

        try {
            $packingList = $this->packingService->createPackingList($pickingList);
            $packingList->load(['warehouse', 'pickingList', 'salesOrder', 'items.product']);

            return response()->json($packingList, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function startPacking(Request $request, $id)
    {
        $list = PackingList::findOrFail($id);

        try {
            $this->packingService->startPacking($list, $request->user()->id);
            return response()->json(['message' => 'Packing started']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function updatePackageDetails(Request $request, $itemId)
    {
        $item = PackingListItem::findOrFail($itemId);

        $validated = $request->validate([
            'package_number' => 'nullable|string',
            'dimensions' => 'nullable|array',
            'weight' => 'nullable|numeric',
            'fragile' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->packingService->updatePackageDetails($item, $validated);
            return response()->json(['message' => 'Package details updated']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function completePacking($id)
    {
        $list = PackingList::findOrFail($id);

        try {
            $this->packingService->completePacking($list);
            return response()->json(['message' => 'Packing completed']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function cancelPacking($id)
    {
        $list = PackingList::findOrFail($id);

        try {
            $this->packingService->cancelPacking($list);
            return response()->json(['message' => 'Packing cancelled']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
        $query = CycleCount::with(['warehouse', 'bin', 'counter', 'reviewer', 'items.product']);

        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->bin_id) {
            $query->byBin($request->bin_id);
        }

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->type) {
            $query->byType($request->type);
        }

        return response()->json($query->paginate(20));
    }

    public function showCycleCount($id)
    {
        $count = CycleCount::with(['warehouse', 'bin', 'counter', 'reviewer', 'items.product', 'items.productVariant', 'items.bin'])
            ->findOrFail($id);

        return response()->json($count);
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
            'bin_id' => $validated['bin_id'],
            'count_number' => 'CC-' . str_pad(CycleCount::count() + 1, 6, '0', STR_PAD_LEFT),
            'type' => $validated['type'],
            'status' => CycleCount::STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($count, 201);
    }

    public function startCycleCount(Request $request, $id)
    {
        $count = CycleCount::findOrFail($id);

        if (!$count->canStart()) {
            return response()->json(['message' => 'Cycle count cannot be started'], 400);
        }

        $count->start($request->user()->id);

        return response()->json(['message' => 'Cycle count started']);
    }

    public function addCycleCountItem(Request $request, $countId)
    {
        $count = CycleCount::findOrFail($countId);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'bin_id' => 'nullable|exists:warehouse_bins,id',
            'expected_quantity' => 'required|integer|min:0',
            'counted_quantity' => 'required|integer|min:0',
            'unit_cost' => 'nullable|numeric',
            'variance_reason' => 'nullable|in:theft,damage,data_entry,unknown',
            'notes' => 'nullable|string',
        ]);

        $item = $count->items()->create($validated);
        $item->calculateVariance();

        return response()->json($item, 201);
    }

    public function completeCycleCount($id)
    {
        $count = CycleCount::findOrFail($id);

        if ($count->status !== CycleCount::STATUS_IN_PROGRESS) {
            return response()->json(['message' => 'Cycle count is not in progress'], 400);
        }

        $count->complete();
        $count->calculateVariance();

        return response()->json(['message' => 'Cycle count completed']);
    }

    public function reviewCycleCount(Request $request, $id)
    {
        $count = CycleCount::findOrFail($id);

        if ($count->status !== CycleCount::STATUS_COMPLETED) {
            return response()->json(['message' => 'Cycle count must be completed before review'], 400);
        }

        $count->review($request->user()->id);

        return response()->json(['message' => 'Cycle count reviewed']);
    }

    public function applyAdjustment(Request $request, $id)
    {
        $count = CycleCount::findOrFail($id);

        if (!$count->requires_adjustment) {
            return response()->json(['message' => 'No adjustment required'], 400);
        }

        $count->applyAdjustment($request->user()->id);

        return response()->json(['message' => 'Adjustment applied']);
    }

    public function cancelCycleCount($id)
    {
        $count = CycleCount::findOrFail($id);
        $count->cancel();

        return response()->json(['message' => 'Cycle count cancelled']);
    }

    // ==================== Warehouses CRUD ====================

    public function indexWarehouses(Request $request)
    {
        $query = Warehouse::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $warehouses = $query->latest()->paginate($request->input('per_page', 20));
        return response()->json($warehouses);
    }

    public function showWarehouse($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        return response()->json($warehouse);
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

        $warehouse = Warehouse::create(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? true,
            'is_primary' => $validated['is_primary'] ?? false,
        ]));

        return response()->json($warehouse, 201);
    }

    public function updateWarehouse(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|max:50|unique:warehouses,code,' . $id,
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

        $warehouse->update($validated);

        return response()->json($warehouse);
    }

    public function destroyWarehouse($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully',
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
}

