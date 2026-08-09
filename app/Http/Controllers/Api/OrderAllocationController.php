<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Warehouse-split suggestions for staff orders.
 *
 * The mobile app builds an internal order from a cart; before submitting it
 * asks the server how each line should be split across warehouses. Staff can
 * accept the suggestion or edit it, and the confirmed plan is persisted as
 * `sales_order_item_allocations` when the order is created/updated.
 */
class OrderAllocationController extends Controller
{
    public function __construct(private OrderAllocationService $service)
    {
    }

    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ], [
            'items.required' => 'لا توجد أصناف للاقتراح',
            'items.min' => 'لا توجد أصناف للاقتراح',
            'items.*.product_id.exists' => 'أحد المنتجات المحددة غير موجود',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
        ]);

        $suggestions = $this->service->suggestAllocations($validated['items']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء اقتراح التوزيع بنجاح',
            'data' => [
                'suggestions' => $suggestions,
            ],
        ]);
    }
}
