<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductWarehouseAssignment;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryCostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryCostMethodController extends Controller
{
    public function __construct(
        protected InventoryCostingService $costingService
    ) {}

    /**
     * Display the current inventory cost calculation configuration.
     */
    public function show(): JsonResponse
    {
        $globalMethod = $this->costingService->getGlobalMethod();
        $supportedMethods = array_values(InventoryCostingService::getSupportedMethods());
        $warehouseMethods = $this->costingService->getWarehouseMethods();

        $warehouses = Warehouse::query()
            ->select('warehouses.id', 'warehouses.name', 'warehouses.code', 'warehouses.is_active')
            ->leftJoin('warehouse_inventory', 'warehouse_inventory.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('products', 'products.id', '=', 'warehouse_inventory.product_id')
            ->selectRaw('COALESCE(SUM(warehouse_inventory.quantity), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(warehouse_inventory.available_quantity), 0) as total_available')
            ->selectRaw('COALESCE(SUM(warehouse_inventory.quantity * COALESCE(products.cost_price, 0)), 0) as total_value')
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.code', 'warehouses.is_active')
            ->orderBy('warehouses.id')
            ->get()
            ->map(function ($warehouse) use ($warehouseMethods, $globalMethod) {
                $custom = $warehouseMethods[$warehouse->id] ?? null;
                $effective = (! empty($custom) && $custom !== 'DEFAULT') ? $custom : $globalMethod;

                return [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'is_active' => (bool) $warehouse->is_active,
                    'custom_method' => $custom ?: 'DEFAULT',
                    'effective_method' => $effective,
                    'total_quantity' => (int) $warehouse->total_quantity,
                    'total_available' => (int) $warehouse->total_available,
                    'total_value' => round((float) $warehouse->total_value, 2),
                ];
            });

        $activeLayersCount = DB::table('inventory_cost_layers')
            ->where('remaining_quantity', '>', 0)
            ->count();

        $totalLayersQuantity = (int) DB::table('inventory_cost_layers')
            ->where('remaining_quantity', '>', 0)
            ->sum('remaining_quantity');

        $totalLayersValue = (float) DB::table('inventory_cost_layers')
            ->where('remaining_quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(remaining_quantity * unit_cost), 0) as value')
            ->value('value');

        return response()->json([
            'success' => true,
            'data' => [
                'current_method' => $globalMethod,
                'available_methods' => $supportedMethods,
                'warehouse_methods' => $warehouseMethods,
                'warehouses' => $warehouses,
                'stats' => [
                    'total_value' => round($this->costingService->valueOnHand(), 2),
                    'total_layers_value' => round($totalLayersValue, 2),
                    'total_layers_quantity' => $totalLayersQuantity,
                    'active_layers_count' => $activeLayersCount,
                    'warehouses_count' => $warehouses->count(),
                ],
            ],
        ]);
    }

    /**
     * Update the cost calculation method settings.
     */
    public function update(Request $request): JsonResponse
    {
        $supportedKeys = array_keys(InventoryCostingService::getSupportedMethods());

        $validated = $request->validate([
            'method' => ['required', 'string', 'in:'.implode(',', $supportedKeys)],
            'warehouse_methods' => ['nullable', 'array'],
            'warehouse_methods.*' => ['nullable', 'string', 'in:DEFAULT,'.implode(',', $supportedKeys)],
            'apply_to_existing' => ['nullable', 'boolean'],
        ]);

        $globalMethod = $validated['method'];
        $this->costingService->setGlobalMethod($globalMethod);

        $warehouseMethods = [];
        if (! empty($validated['warehouse_methods'])) {
            foreach ($validated['warehouse_methods'] as $wId => $wMethod) {
                if (! empty($wMethod) && $wMethod !== 'DEFAULT' && in_array($wMethod, $supportedKeys, true)) {
                    $warehouseMethods[(int) $wId] = $wMethod;
                }
            }
        }
        $this->costingService->setWarehouseMethods($warehouseMethods);

        $appliedCount = 0;
        $recalculatedLayers = 0;

        if (! empty($validated['apply_to_existing'])) {
            // Apply effective method to warehouse_inventory records
            $warehouses = Warehouse::pluck('id');
            foreach ($warehouses as $wId) {
                $effectiveMethod = $warehouseMethods[$wId] ?? $globalMethod;

                $updated = WarehouseInventory::where('warehouse_id', $wId)
                    ->update(['cost_basis' => $effectiveMethod]);

                ProductWarehouseAssignment::where('warehouse_id', $wId)
                    ->update(['cost_basis' => $effectiveMethod]);

                $appliedCount += $updated;

                if ($effectiveMethod === WarehouseInventory::COST_BASIS_WEIGHTED_AVERAGE) {
                    $recalculatedLayers += $this->costingService->recalculateWeightedAverageForWarehouse($wId);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث طريقة احتساب التكلفة بنجاح',
            'data' => [
                'current_method' => $globalMethod,
                'warehouse_methods' => $warehouseMethods,
                'applied_to_records_count' => $appliedCount,
                'recalculated_layers_count' => $recalculatedLayers,
            ],
        ]);
    }
}
