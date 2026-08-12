<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductWarehouseAssignment;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductWarehouseAssignmentController extends Controller
{
    /**
     * Get all product-warehouse assignments
     */
    public function index(Request $request)
    {
        $query = ProductWarehouseAssignment::with(['product', 'variant', 'warehouse', 'supplier', 'primaryBin']);

        // Filters
        if ($request->has('product_id')) {
            $query->forProduct($request->product_id);
        }

        if ($request->has('warehouse_id')) {
            $query->forWarehouse($request->warehouse_id);
        }

        if ($request->has('is_active')) {
            if ($request->is_active) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if ($request->has('from_date')) {
            $query->where('effective_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('effective_date', '<=', $request->to_date);
        }

        $assignments = $query->orderBy('effective_date', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    /**
     * Get a specific assignment
     */
    public function show($id)
    {
        $assignment = ProductWarehouseAssignment::with([
            'product',
            'variant',
            'warehouse',
            'supplier',
            'primaryBin',
            'binAssignments.bin',
            'inventory.bin',
            'inventory.batches',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    /**
     * Create a new product-warehouse assignment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'effective_date' => 'required|date|after_or_equal:today',
            'expiry_date' => 'nullable|date|after:effective_date',
            'is_active' => 'boolean',
            'replenishment_method' => 'required|in:purchase,manufacture,internal_distribution,warehouse_transfer',
            'planning_method' => 'required|in:rop,mrp',
            'min_stock_level' => 'required|integer|min:0',
            'max_stock_level' => 'required|integer|min:0|gte:min_stock_level',
            'safety_stock' => 'required|integer|min:0|lte:min_stock_level',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'lead_time_days' => 'required|integer|min:1',
            'primary_bin_id' => 'nullable|exists:warehouse_bins,id',
            'putaway_strategy' => 'required|in:fifo,fefo,similarity,weight_based,volume_based',
            'auto_reorder_enabled' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Check for duplicate assignment
        $exists = ProductWarehouseAssignment::where('product_id', $validated['product_id'])
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('effective_date', $validated['effective_date'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'assignment' => ['هذا المنتج مرتبط بالفعل بهذا المستودع في هذا التاريخ'],
            ]);
        }

        $assignment = ProductWarehouseAssignment::create($validated);

        // Initialize inventory record if it doesn't exist
        $inventory = WarehouseInventory::firstOrCreate(
            [
                'warehouse_id' => $assignment->warehouse_id,
                'product_id' => $assignment->product_id,
                'product_variant_id' => $assignment->product_variant_id,
            ],
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
                'available_quantity' => 0,
                'reorder_point' => $assignment->min_stock_level,
                'safety_stock' => $assignment->safety_stock,
                'lead_time_days' => $assignment->lead_time_days,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء ربط المنتج بالمستودع بنجاح',
            'data' => $assignment->load(['product', 'warehouse', 'inventory']),
        ], 201);
    }

    /**
     * Update an assignment
     */
    public function update(Request $request, $id)
    {
        $assignment = ProductWarehouseAssignment::findOrFail($id);

        $validated = $request->validate([
            'effective_date' => 'nullable|date|after_or_equal:today',
            'expiry_date' => 'nullable|date|after:effective_date',
            'is_active' => 'boolean',
            'replenishment_method' => 'nullable|in:purchase,manufacture,internal_distribution,warehouse_transfer',
            'planning_method' => 'nullable|in:rop,mrp',
            'min_stock_level' => 'nullable|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0|gte:min_stock_level',
            'safety_stock' => 'nullable|integer|min:0|lte:min_stock_level',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'lead_time_days' => 'nullable|integer|min:1',
            'primary_bin_id' => 'nullable|exists:warehouse_bins,id',
            'putaway_strategy' => 'nullable|in:fifo,fefo,similarity,weight_based,volume_based',
            'auto_reorder_enabled' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $assignment->update($validated);

        // Sync inventory reorder point if changed
        if (isset($validated['min_stock_level']) || isset($validated['safety_stock'])) {
            $inventory = $assignment->inventory->first();
            if ($inventory) {
                $inventory->reorder_point = $validated['min_stock_level'] ?? $inventory->reorder_point;
                $inventory->safety_stock = $validated['safety_stock'] ?? $inventory->safety_stock;
                $inventory->lead_time_days = $validated['lead_time_days'] ?? $inventory->lead_time_days;
                $inventory->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الربط بنجاح',
            'data' => $assignment->load(['product', 'warehouse']),
        ]);
    }

    /**
     * Delete an assignment
     */
    public function destroy($id)
    {
        $assignment = ProductWarehouseAssignment::findOrFail($id);

        // Check if there's inventory
        if ($assignment->current_stock > 0) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف الربط لأنه يحتوي على مخزون',
            ], 400);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الربط بنجاح',
        ]);
    }

    /**
     * Get real-time stock balance for a product across all warehouses
     */
    public function getRealTimeStockBalance($productId)
    {
        $assignments = ProductWarehouseAssignment::active()
            ->forProduct($productId)
            ->with(['warehouse', 'inventory'])
            ->get();

        $stockData = $assignments->map(function ($assignment) {
            return [
                'warehouse_id' => $assignment->warehouse_id,
                'warehouse_name' => $assignment->warehouse->name,
                'warehouse_code' => $assignment->warehouse->code,
                'warehouse_location' => [
                    'city' => $assignment->warehouse->city,
                    'latitude' => $assignment->warehouse->latitude,
                    'longitude' => $assignment->warehouse->longitude,
                ],
                'quantity' => $assignment->current_stock,
                'available_quantity' => $assignment->available_stock,
                'reserved_quantity' => $assignment->inventory->sum('reserved_quantity'),
                'min_stock_level' => $assignment->min_stock_level,
                'max_stock_level' => $assignment->max_stock_level,
                'safety_stock' => $assignment->safety_stock,
                'is_below_min' => $assignment->isBelowMinStock(),
                'is_below_reorder' => $assignment->isBelowReorderPoint(),
                'last_updated' => $assignment->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $productId,
                'total_stock' => $assignments->sum('current_stock'),
                'total_available' => $assignments->sum('available_stock'),
                'warehouses' => $stockData,
            ],
        ]);
    }

    /**
     * Update stock balance after putaway/picking operations
     */
    public function updateStockBalance(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'bin_id' => 'nullable|exists:warehouse_bins,id',
            'quantity_change' => 'required|integer',
            'movement_type' => 'required|in:putaway,picking,transfer,adjustment',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'batch_number' => 'nullable|string',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $quantityChange = (int) $validated['quantity_change'];

            // Everything goes through InventoryService so the per-bin warehouse
            // row, the movement trail and products.stock_quantity stay in step.
            // putaway/transfer/adjustment add, picking takes.
            $movementType = match ($validated['movement_type']) {
                'picking' => StockMovement::TYPE_OUT,
                'adjustment' => StockMovement::TYPE_ADJUSTMENT,
                default => StockMovement::TYPE_IN,
            };

            $signedChange = $validated['movement_type'] === 'picking' ? -$quantityChange : $quantityChange;

            $movement = app(InventoryService::class)->move(
                $validated['product_id'],
                $signedChange,
                $validated['warehouse_id'],
                $movementType,
                [
                    'key' => isset($validated['reference_type'], $validated['reference_id'])
                        ? $validated['reference_type'].':'.$validated['reference_id'].':bin:'.($validated['bin_id'] ?? 0)
                        : null,
                    'reference' => $validated['reference_type'] ?? null,
                    'source' => 'assignment_operation',
                    'reason' => $validated['notes'] ?? ('عملية '.$validated['movement_type']),
                    'condition' => 'available',
                    'bin_id' => $validated['bin_id'] ?? null,
                    'allow_negative' => $validated['movement_type'] === 'adjustment',
                    'created_by' => auth()->id(),
                ]
            );

            $inventory = WarehouseInventory::where('product_id', $validated['product_id'])
                ->where('warehouse_id', $validated['warehouse_id'])
                ->where('product_variant_id', $validated['product_variant_id'] ?? null)
                ->where('bin_id', $validated['bin_id'] ?? null)
                ->first();

            // Update batch and expiry if provided
            if ($inventory && isset($validated['batch_number'])) {
                $inventory->batch_number = $validated['batch_number'];
            }
            if ($inventory && isset($validated['expiry_date'])) {
                $inventory->expiry_date = $validated['expiry_date'];
            }
            $inventory?->save();

            // Update bin utilization
            if ($inventory && $inventory->bin) {
                $inventory->bin->updateUtilization($quantityChange);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الرصيد بنجاح',
                'data' => [
                    'inventory_id' => $inventory?->id,
                    'new_quantity' => $inventory?->quantity ?? 0,
                    'new_available' => $inventory ? $inventory->available_stock : 0,
                    'movement_id' => $movement?->id,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get assignments that need reordering
     */
    public function getReorderAlerts()
    {
        $assignments = ProductWarehouseAssignment::active()
            ->with(['product', 'warehouse', 'supplier'])
            ->get()
            ->filter(function ($assignment) {
                return $assignment->isBelowReorderPoint() && $assignment->auto_reorder_enabled;
            });

        $alerts = $assignments->map(function ($assignment) {
            return [
                'assignment_id' => $assignment->id,
                'product' => [
                    'id' => $assignment->product->id,
                    'name' => $assignment->product->name,
                    'sku' => $assignment->product->sku,
                ],
                'warehouse' => [
                    'id' => $assignment->warehouse->id,
                    'name' => $assignment->warehouse->name,
                    'code' => $assignment->warehouse->code,
                ],
                'current_stock' => $assignment->available_stock,
                'min_stock_level' => $assignment->min_stock_level,
                'safety_stock' => $assignment->safety_stock,
                'reorder_quantity' => $assignment->calculateReorderQuantity(),
                'replenishment_method' => $assignment->replenishment_method,
                'supplier' => $assignment->supplier ? [
                    'id' => $assignment->supplier->id,
                    'name' => $assignment->supplier->name,
                ] : null,
                'lead_time_days' => $assignment->lead_time_days,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Get recommended warehouse for customer based on proximity
     */
    public function getRecommendedWarehouse(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'customer_latitude' => 'required|numeric',
            'customer_longitude' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);

        $assignments = ProductWarehouseAssignment::active()
            ->forProduct($validated['product_id'])
            ->with(['warehouse'])
            ->whereAvailableStock('>=', $validated['quantity'])
            ->get();

        if ($assignments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد مخزون متاح في أي مستودع',
            ], 404);
        }

        // Calculate distance for each warehouse
        $recommendations = $assignments->map(function ($assignment) use ($validated) {
            $distance = $this->calculateDistance(
                $assignment->warehouse->latitude,
                $assignment->warehouse->longitude,
                $validated['customer_latitude'],
                $validated['customer_longitude']
            );

            return [
                'assignment' => $assignment,
                'distance_km' => $distance,
            ];
        })->sortBy('distance_km');

        return response()->json([
            'success' => true,
            'data' => $recommendations->first(),
        ]);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
