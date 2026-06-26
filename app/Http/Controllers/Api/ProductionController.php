<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductionOrder::with(['product', 'creator']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('product_id') && $request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        $productionOrders = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Production orders retrieved successfully',
            'data' => [
                'production_orders' => $productionOrders->items(),
                'pagination' => [
                    'current_page' => $productionOrders->currentPage(),
                    'last_page' => $productionOrders->lastPage(),
                    'per_page' => $productionOrders->perPage(),
                    'total' => $productionOrders->total(),
                    'has_more_pages' => $productionOrders->hasMorePages(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['order_number'] = 'PROD-' . str_pad(ProductionOrder::count() + 1, 6, '0', STR_PAD_LEFT);
        $validated['status'] = ProductionOrder::STATUS_PENDING;
        $validated['created_by'] = auth()->id();

        $productionOrder = ProductionOrder::create($validated);
        $productionOrder->load(['product', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء أمر الإنتاج بنجاح',
            'data' => $productionOrder
        ], 201);
    }

    public function show(ProductionOrder $productionOrder)
    {
        $productionOrder->load(['product', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Production order retrieved successfully',
            'data' => $productionOrder
        ]);
    }

    public function update(Request $request, ProductionOrder $productionOrder)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $productionOrder->update($validated);
        $productionOrder->load(['product', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث أمر الإنتاج بنجاح',
            'data' => $productionOrder
        ]);
    }

    public function destroy(ProductionOrder $productionOrder)
    {
        $productionOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف أمر الإنتاج بنجاح',
            'data' => null
        ]);
    }

    public function updateStatus(Request $request, ProductionOrder $productionOrder)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ]);

        $productionOrder->update(['status' => $validated['status']]);

        // If completed, add to stock
        if ($validated['status'] === ProductionOrder::STATUS_COMPLETED) {
            $product = $productionOrder->product;
            if ($product) {
                $product->increment('stock_quantity', $productionOrder->quantity);
            }
        }

        $productionOrder->load(['product', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة أمر الإنتاج بنجاح',
            'data' => $productionOrder
        ]);
    }

    public function stats()
    {
        $totalOrders = ProductionOrder::count();
        $pendingOrders = ProductionOrder::where('status', ProductionOrder::STATUS_PENDING)->count();
        $inProgressOrders = ProductionOrder::where('status', ProductionOrder::STATUS_IN_PROGRESS)->count();
        $completedOrders = ProductionOrder::where('status', ProductionOrder::STATUS_COMPLETED)->count();
        $cancelledOrders = ProductionOrder::where('status', ProductionOrder::STATUS_CANCELLED)->count();

        return response()->json([
            'success' => true,
            'message' => 'Production statistics retrieved successfully',
            'data' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'in_progress_orders' => $inProgressOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
            ]
        ]);
    }
}
