<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductSerialNumber;
use App\Models\ReorderAlert;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\InventoryAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnhancedInventoryController extends Controller
{
    protected InventoryAllocationService $allocationService;

    public function __construct(InventoryAllocationService $allocationService)
    {
        $this->allocationService = $allocationService;
    }

    public function getLocations(Request $request)
    {
        $query = Warehouse::query();

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $warehouses = $query->with(['inventory' => function($q) {
            $q->with('product');
        }])->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses,
        ]);
    }

    public function getLocationStock($warehouseId)
    {
        $warehouse = Warehouse::with([
            'inventory' => function($q) {
                $q->with('product', 'variant', 'batches');
            },
            'batches',
            'serialNumbers'
        ])->findOrFail($warehouseId);

        return response()->json([
            'success' => true,
            'data' => $warehouse,
        ]);
    }

    public function getProductStock(Request $request, $productId)
    {
        $variantId = $request->query('variant_id');
        
        $inventorySummary = $this->allocationService->getInventorySummary($productId, $variantId);

        return response()->json([
            'success' => true,
            'data' => $inventorySummary,
        ]);
    }

    public function getLowStockAlerts(Request $request)
    {
        $query = ReorderAlert::with(['product', 'warehouse', 'variant'])
            ->pending()
            ->orderBy('severity', 'desc')
            ->orderBy('alerted_at', 'desc');

        if ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        $alerts = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    public function resolveAlert(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:ordered,resolved,dismissed',
        ]);

        $alert = ReorderAlert::with(['product', 'warehouse'])->findOrFail($id);

        try {
            DB::beginTransaction();

            match($request->action) {
                'ordered' => $alert->markAsOrdered(auth()->id()),
                'resolved' => $alert->markAsResolved(auth()->id()),
                'dismissed' => $alert->dismiss(auth()->id()),
            };

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة التنبيه بنجاح',
                'data' => $alert,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function getBatches(Request $request)
    {
        $query = ProductBatch::with(['product', 'variant', 'warehouse']);

        if ($request->has('product_id')) {
            $query->byProduct($request->product_id);
        }

        if ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('expiring_soon')) {
            $query->expiringSoon($request->expiring_soon ?? 30);
        }

        $batches = $query->orderBy('expiry_date', 'asc')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    public function createBatch(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'batch_number' => 'required|unique:product_batches',
            'quantity' => 'required|integer|min:1',
            'expiry_date' => 'required|date|after:today',
            'unit_cost' => 'required|numeric|min:0',
            'manufacturing_date' => 'nullable|date',
            'product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        try {
            DB::beginTransaction();

            $batch = ProductBatch::create([
                'product_id' => $request->product_id,
                'warehouse_id' => $request->warehouse_id,
                'batch_number' => $request->batch_number,
                'quantity' => $request->quantity,
                'expiry_date' => $request->expiry_date,
                'unit_cost' => $request->unit_cost,
                'manufacturing_date' => $request->manufacturing_date,
                'product_variant_id' => $request->product_variant_id,
                'status' => ProductBatch::STATUS_AVAILABLE,
            ]);

            // Update or create warehouse inventory
            $inventory = WarehouseInventory::firstOrCreate(
                [
                    'product_id' => $request->product_id,
                    'warehouse_id' => $request->warehouse_id,
                    'product_variant_id' => $request->product_variant_id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'reorder_point' => 10,
                    'safety_stock' => 5,
                    'cost_basis' => WarehouseInventory::COST_BASIS_FIFO,
                ]
            );

            $inventory->increment('quantity', $request->quantity);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الدفعة بنجاح',
                'data' => $batch,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function getSerialNumbers(Request $request)
    {
        $query = ProductSerialNumber::with(['product', 'variant', 'warehouse', 'batch']);

        if ($request->has('product_id')) {
            $query->byProduct($request->product_id);
        }

        if ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('serial_number')) {
            $query->bySerialNumber($request->serial_number);
        }

        $serialNumbers = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $serialNumbers,
        ]);
    }

    public function createSerialNumbers(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'serial_numbers' => 'required|array|min:1',
            'serial_numbers.*' => 'required|string|unique:product_serial_numbers,serial_number',
            'batch_id' => 'nullable|exists:product_batches,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        try {
            DB::beginTransaction();

            $created = [];
            foreach ($request->serial_numbers as $serialNumber) {
                $serial = ProductSerialNumber::create([
                    'product_id' => $request->product_id,
                    'warehouse_id' => $request->warehouse_id,
                    'serial_number' => $serialNumber,
                    'batch_id' => $request->batch_id,
                    'product_variant_id' => $request->product_variant_id,
                    'status' => ProductSerialNumber::STATUS_IN_STOCK,
                ]);
                $created[] = $serial;
            }

            // Update warehouse inventory count
            $inventory = WarehouseInventory::firstOrCreate(
                [
                    'product_id' => $request->product_id,
                    'warehouse_id' => $request->warehouse_id,
                    'product_variant_id' => $request->product_variant_id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'reorder_point' => 10,
                    'safety_stock' => 5,
                ]
            );

            $inventory->increment('quantity', count($request->serial_numbers));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الأرقام التسلسلية بنجاح',
                'data' => $created,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function allocateInventory(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
            'product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $productId = $request->product_id;
        $warehouseId = $request->warehouse_id;
        $quantity = $request->quantity;
        $variantId = $request->product_variant_id;

        // Check availability
        if (!$this->allocationService->checkAvailability($productId, $quantity, $warehouseId, $variantId)) {
            return response()->json([
                'success' => false,
                'message' => 'المخزون غير كافي',
                'data' => null,
            ], 422);
        }

        // Allocate inventory
        $allocations = $this->allocationService->allocate($productId, $quantity, $warehouseId, $variantId);

        return response()->json([
            'success' => true,
            'message' => 'تم تخصيص المخزون بنجاح',
            'data' => $allocations,
        ]);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
            'product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $isAvailable = $this->allocationService->checkAvailability(
            $request->product_id,
            $request->quantity,
            $request->warehouse_id,
            $request->product_variant_id
        );

        $totalAvailable = $this->allocationService->getTotalAvailableQuantity(
            $request->product_id,
            $request->product_variant_id
        );

        return response()->json([
            'success' => true,
            'data' => [
                'is_available' => $isAvailable,
                'total_available' => $totalAvailable,
                'requested_quantity' => $request->quantity,
            ],
        ]);
    }

    public function updateReorderPoints(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        try {
            $query = WarehouseInventory::query();

            if ($request->has('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            $inventories = $query->get();

            foreach ($inventories as $inventory) {
                $inventory->updateAverageDailySales();
                $newReorderPoint = $inventory->calculateDynamicReorderPoint();
                $inventory->reorder_point = $newReorderPoint;
                $inventory->save();

                // Create or update reorder alert if below reorder point
                if ($inventory->isBelowReorderPoint()) {
                    $alert = ReorderAlert::firstOrCreate(
                        [
                            'product_id' => $inventory->product_id,
                            'warehouse_id' => $inventory->warehouse_id,
                            'product_variant_id' => $inventory->product_variant_id,
                            'status' => ReorderAlert::STATUS_PENDING,
                        ],
                        [
                            'current_quantity' => $inventory->available_stock,
                            'reorder_point' => $inventory->reorder_point,
                            'safety_stock' => $inventory->safety_stock,
                            'alerted_at' => now(),
                        ]
                    );

                    $alert->calculateSeverity();
                    $alert->calculateSuggestedOrderQuantity();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث نقاط إعادة الطلب بنجاح',
                'data' => [
                    'updated_count' => $inventories->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}
