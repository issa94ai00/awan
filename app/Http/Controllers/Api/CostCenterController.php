<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The parts of the business a figure can belong to.
 *
 * One is created for every warehouse, because that is the division the system
 * already knows and can attribute a posting to without anybody filling in a
 * field. The rest are for divisions that hold no stock and still carry costs —
 * an administration, a delivery fleet — which is why a centre is its own record
 * rather than the warehouse itself.
 */
class CostCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $centers = CostCenter::with('warehouse:id,name')
            ->withCount('journalEntryLines')
            ->when($request->filled('is_active'), fn ($q) => $q->where(
                'is_active',
                filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
            ))
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Cost centres retrieved successfully',
            'data' => [
                'centers' => $centers,
                // Warehouses with no centre yet: the only ones worth offering,
                // since a warehouse can be claimed by one centre only.
                'available_warehouses' => Warehouse::whereNotIn(
                    'id',
                    CostCenter::whereNotNull('warehouse_id')->pluck('warehouse_id')
                )->get(['id', 'name']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:cost_centers,code',
            'name' => 'required|string|max:255',
            // Unique in the table: two centres claiming one warehouse would make
            // the attribution depend on which row was read first.
            'warehouse_id' => 'nullable|exists:warehouses,id|unique:cost_centers,warehouse_id',
            'notes' => 'nullable|string|max:1000',
        ], [
            'warehouse_id.unique' => 'هذا المستودع مرتبط بمركز تكلفة آخر.',
            'code.unique' => 'رمز المركز مستخدم.',
        ]);

        $center = CostCenter::create($validated + ['is_active' => true]);
        CostCenter::forgetWarehouseCache();

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء مركز التكلفة',
            'data' => $center->load('warehouse:id,name'),
        ], 201);
    }

    public function update(Request $request, CostCenter $costCenter): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:cost_centers,code,'.$costCenter->id,
            'name' => 'required|string|max:255',
            'warehouse_id' => 'nullable|exists:warehouses,id|unique:cost_centers,warehouse_id,'.$costCenter->id,
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Re-pointing a centre at a different warehouse changes what future
        // postings attribute to it, and leaves everything already posted where
        // it was — which is right: those figures happened where they happened.
        $costCenter->update($validated);
        CostCenter::forgetWarehouseCache();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث مركز التكلفة',
            'data' => $costCenter->refresh()->load('warehouse:id,name'),
        ]);
    }

    /**
     * A centre that has carried figures is deactivated, not deleted.
     *
     * Deleting one detaches it from every line that named it, so a report of a
     * past period would silently move that activity into "unattributed" — the
     * books would not change, but the analysis of a closed month would.
     */
    public function destroy(CostCenter $costCenter): JsonResponse
    {
        if ($costCenter->journalEntryLines()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يُحذف مركز رُحّلت عليه قيود — سيفقد تحليل الفترات السابقة نسبته. عطّله بدل حذفه.',
                'data' => null,
            ], 422);
        }

        $costCenter->delete();
        CostCenter::forgetWarehouseCache();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف مركز التكلفة',
            'data' => null,
        ]);
    }
}
