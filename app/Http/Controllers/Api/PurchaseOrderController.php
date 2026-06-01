<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['supplier', 'items.product']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $purchaseOrders = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Purchase orders retrieved successfully',
            'data' => [
                'orders' => $purchaseOrders->items(),
                'pagination' => [
                    'current_page' => $purchaseOrders->currentPage(),
                    'last_page' => $purchaseOrders->lastPage(),
                    'per_page' => $purchaseOrders->perPage(),
                    'total' => $purchaseOrders->total(),
                    'has_more_pages' => $purchaseOrders->hasMorePages(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'status' => 'nullable|string|in:pending,confirmed,ordered,received,cancelled',
            'due_date' => 'nullable|date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        $orderNumber = 'PO-' . str_pad(PurchaseOrder::count() + 1, 6, '0', STR_PAD_LEFT);
        $validated['order_number'] = $orderNumber;
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['created_by'] = auth()->id();

        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $subtotal += $item['unit_price'] * $item['quantity'];
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal + ($validated['tax'] ?? 0) - ($validated['discount'] ?? 0);

        $order = PurchaseOrder::create($validated);

        foreach ($validated['items'] as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['unit_price'] * $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ]);
        }

        $order->load(['supplier', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase order created successfully',
            'data' => $order,
        ], 201);
    }

    public function show(PurchaseOrder $order): JsonResponse
    {
        $order->load(['supplier', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase order retrieved successfully',
            'data' => $order,
        ]);
    }

    public function update(Request $request, PurchaseOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'status' => 'required|string|in:pending,confirmed,ordered,received,cancelled',
            'due_date' => 'nullable|date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $subtotal += $item['unit_price'] * $item['quantity'];
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal + ($validated['tax'] ?? 0) - ($validated['discount'] ?? 0);

        $order->update($validated);
        $order->items()->delete();

        foreach ($validated['items'] as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['unit_price'] * $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ]);
        }

        $order->load(['supplier', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase order updated successfully',
            'data' => $order,
        ]);
    }

    public function destroy(PurchaseOrder $order): JsonResponse
    {
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase order deleted successfully',
            'data' => null,
        ]);
    }
}
