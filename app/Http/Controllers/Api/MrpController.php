<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Inventory\MrpService;
use Illuminate\Http\Request;

class MrpController extends Controller
{
    private MrpService $mrpService;

    public function __construct(MrpService $mrpService)
    {
        $this->mrpService = $mrpService;
    }

    /**
     * Run MRP for a specific product
     */
    public function runForProduct(Request $request, $productId)
    {
        $horizonDays = $request->get('horizon_days', 90);
        
        $results = $this->mrpService->runMrpForProduct($productId, $horizonDays);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Run MRP for a specific warehouse
     */
    public function runForWarehouse(Request $request, $warehouseId)
    {
        $horizonDays = $request->get('horizon_days', 90);
        
        $results = $this->mrpService->runMrpForWarehouse($warehouseId, $horizonDays);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Run MRP for all products across all warehouses
     */
    public function runForAll(Request $request)
    {
        $horizonDays = $request->get('horizon_days', 90);
        
        // This would be better as a background job for large systems
        $allAssignments = \App\Models\ProductWarehouseAssignment::active()
            ->where('planning_method', 'mrp')
            ->pluck('id');

        $results = [];
        foreach ($allAssignments as $assignmentId) {
            $assignment = \App\Models\ProductWarehouseAssignment::find($assignmentId);
            if ($assignment) {
                $results[] = $this->mrpService->calculateWarehouseMrp($assignment, $horizonDays);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $results,
            'total_processed' => count($results),
        ]);
    }

    /**
     * Execute MRP recommendations
     */
    public function executeRecommendations(Request $request, $assignmentId)
    {
        $recommendationTypes = $request->get('recommendation_types', []);
        
        $executedOrders = $this->mrpService->executeMrpRecommendations($assignmentId, $recommendationTypes);

        return response()->json([
            'success' => true,
            'message' => 'تم تنفيذ توصيات MRP بنجاح',
            'data' => $executedOrders,
        ]);
    }

    /**
     * Get MRP summary for dashboard
     */
    public function getSummary()
    {
        $mrpAssignments = \App\Models\ProductWarehouseAssignment::active()
            ->where('planning_method', 'mrp')
            ->with(['product', 'warehouse'])
            ->get();

        $summary = [
            'total_mrp_assignments' => $mrpAssignments->count(),
            'below_min_stock' => 0,
            'below_safety_stock' => 0,
            'urgent_orders' => 0,
            'planned_orders' => 0,
        ];

        foreach ($mrpAssignments as $assignment) {
            if ($assignment->isBelowMinStock()) {
                $summary['below_min_stock']++;
            }
            if ($assignment->available_stock < $assignment->safety_stock) {
                $summary['below_safety_stock']++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
