<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        try {
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
            ], [
                'supplier_id.required' => 'يجب اختيار المورد',
                'supplier_id.exists' => 'المورد المحدد غير موجود',
                'items.required' => 'يجب إضافة منتج واحد على الأقل',
                'items.*.product_id.required' => 'يجب تحديد المنتج',
                'items.*.product_id.exists' => 'المنتج المحدد غير موجود',
                'items.*.quantity.required' => 'يجب تحديد الكمية',
                'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
                'items.*.unit_price.required' => 'يجب تحديد سعر الوحدة',
                'items.*.unit_price.min' => 'سعر الوحدة يجب أن يكون 0 على الأقل',
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

            // The header carries totals computed from the lines, so the two must
            // land together. Written separately, a failure inside the loop left
            // an order whose subtotal described items that do not exist.
            $order = DB::transaction(function () use ($validated) {
                $order = PurchaseOrder::create($validated);

                foreach ($validated['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'] ?? ($product ? $product->name : 'Unknown Product'),
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['unit_price'] * $item['quantity'],
                        'notes' => $item['notes'] ?? null,
                    ]);
                }

                return $order;
            });

            $order->load(['supplier', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء أمر الشراء بنجاح',
                'data' => $order,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء أمر الشراء',
                'error' => $e->getMessage(),
            ], 500);
        }
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

        /*
         * Editing an order clears its lines and writes them again. Without a
         * transaction that is destructive rather than merely inconsistent: a
         * failure between the delete and the last insert leaves the order with
         * fewer lines than it had — or none — and no way to recover them.
         */
        DB::transaction(function () use ($order, $validated) {
            $order->update($validated);
            $order->items()->delete();

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'] ?? ($product ? $product->name : 'Unknown Product'),
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['unit_price'] * $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        });

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
