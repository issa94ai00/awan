<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Inventory\PickingService;
use Illuminate\Http\Request;

class PickingController extends Controller
{
    private PickingService $pickingService;

    public function __construct(PickingService $pickingService)
    {
        $this->pickingService = $pickingService;
    }

    /**
     * Get best warehouse for picking based on customer location
     */
    public function getBestWarehouse(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'customer_latitude' => 'required|numeric',
            'customer_longitude' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);

        $bestWarehouse = $this->pickingService->getBestWarehouseForPicking(
            $validated['product_id'],
            $validated['customer_latitude'],
            $validated['customer_longitude'],
            $validated['quantity']
        );

        if (!$bestWarehouse) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد مخزون متاح في أي مستودع',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $bestWarehouse,
        ]);
    }

    /**
     * Generate picking plan for a sales order
     */
    public function generatePickingPlan(Request $request, $salesOrderId)
    {
        $customerLatitude = $request->get('customer_latitude');
        $customerLongitude = $request->get('customer_longitude');

        $pickingPlan = $this->pickingService->generatePickingPlan(
            $salesOrderId,
            $customerLatitude,
            $customerLongitude
        );

        if (!$pickingPlan) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على بنود أمر المبيعات',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pickingPlan,
        ]);
    }

    /**
     * Optimize picking route within a warehouse
     */
    public function optimizeRoute($pickingListId)
    {
        $optimizedRoute = $this->pickingService->optimizePickingRoute($pickingListId);

        if (!$optimizedRoute) {
            return response()->json([
                'success' => false,
                'message' => 'قائمة الانتقاء غير موجودة أو فارغة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $optimizedRoute,
        ]);
    }

    /**
     * Confirm picking completion
     */
    public function confirmPicking(Request $request, $pickingListId)
    {
        $validated = $request->validate([
            'picker_id' => 'required|exists:users,id',
        ]);

        $result = $this->pickingService->confirmPicking($pickingListId, $validated['picker_id']);

        return response()->json($result);
    }

    /**
     * Get picking lists for a warehouse
     */
    public function getPickingLists(Request $request)
    {
        $warehouseId = $request->get('warehouse_id');
        $status = $request->get('status');
        $date = $request->get('date');

        $query = \App\Models\PickingList::with(['items.product', 'items.bin', 'warehouse', 'salesOrder']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($date) {
            $query->whereDate('created_at', $date);
        }

        $pickingLists = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $pickingLists,
        ]);
    }

    /**
     * Get picking list details
     */
    public function getPickingList($id)
    {
        $pickingList = \App\Models\PickingList::with([
            'items.product',
            'items.bin',
            'warehouse',
            'salesOrder',
            'assignedTo'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $pickingList,
        ]);
    }

    /**
     * Assign picking list to a picker
     */
    public function assignPicker(Request $request, $pickingListId)
    {
        $validated = $request->validate([
            'picker_id' => 'required|exists:users,id',
        ]);

        $pickingList = \App\Models\PickingList::findOrFail($pickingListId);
        $pickingList->update([
            'assigned_to' => $validated['picker_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين المنتج بنجاح',
            'data' => $pickingList,
        ]);
    }

    /**
     * Update picking item status
     */
    public function updatePickingItem(Request $request, $itemId)
    {
        $validated = $request->validate([
            'quantity_picked' => 'required|integer|min:0',
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        $pickingItem = \App\Models\PickingListItem::findOrFail($itemId);
        $pickingItem->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بند الانتقاء بنجاح',
            'data' => $pickingItem,
        ]);
    }
}
