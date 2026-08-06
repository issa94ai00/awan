<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Http\Request;

class PurchaseReceiptController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseReceipt::with(['purchaseOrder', 'supplier', 'creator', 'items.product']);

        if ($request->has('supplier_id') && $request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $receipts = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Purchase receipts retrieved successfully',
            'data' => [
                'receipts' => $receipts->items(),
                'pagination' => [
                    'current_page' => $receipts->currentPage(),
                    'last_page' => $receipts->lastPage(),
                    'per_page' => $receipts->perPage(),
                    'total' => $receipts->total(),
                    'has_more_pages' => $receipts->hasMorePages(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'receipt_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $validated['receipt_number'] = 'PR-' . str_pad(PurchaseReceipt::count() + 1, 6, '0', STR_PAD_LEFT);
        $validated['created_by'] = auth()->id();

        $receipt = PurchaseReceipt::create($validated);

        foreach ($request->items as $item) {
            $receipt->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء إيصال الاستلام بنجاح',
            'data' => $receipt
        ], 201);
    }

    public function show(PurchaseReceipt $receipt)
    {
        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase receipt retrieved successfully',
            'data' => $receipt
        ]);
    }

    public function update(Request $request, PurchaseReceipt $receipt)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'receipt_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $receipt->update($validated);

        $receipt->items()->delete();
        foreach ($request->items as $item) {
            $receipt->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث إيصال الاستلام بنجاح',
            'data' => $receipt
        ]);
    }

    public function destroy(PurchaseReceipt $receipt)
    {
        $receipt->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف إيصال الاستلام بنجاح',
            'data' => null
        ]);
    }
}
