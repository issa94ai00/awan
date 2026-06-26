<?php

namespace App\Http\Controllers\Admin;

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
        $query = PurchaseReceipt::with(['purchaseOrder', 'supplier', 'creator']);

        if ($request->has('search') && $request->search) {
            $query->where('receipt_number', 'like', '%' . $request->search . '%')
                ->orWhereHas('supplier', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
        }

        $receipts = $query->latest()->paginate(20);
        $suppliers = Supplier::all();

        return view('admin.purchase-receipts.index', compact('receipts', 'suppliers'));
    }

    public function create()
    {
        $purchaseOrders = PurchaseOrder::where('status', 'confirmed')->get();
        $suppliers = Supplier::all();
        $products = Product::where('is_active', true)->get();
        return view('admin.purchase-receipts.create', compact('purchaseOrders', 'suppliers', 'products'));
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

        // Create receipt items (stock updates happen automatically via model events)
        foreach ($request->items as $item) {
            $receipt->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        return redirect()->route('admin.purchase-receipts.index')
            ->with('success', 'تم إنشاء إيصال الاستلام بنجاح');
    }

    public function show(PurchaseReceipt $receipt)
    {
        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product']);
        return view('admin.purchase-receipts.show', compact('receipt'));
    }

    public function edit(PurchaseReceipt $receipt)
    {
        $receipt->load('items.product');
        $purchaseOrders = PurchaseOrder::where('status', 'confirmed')->get();
        $suppliers = Supplier::all();
        $products = Product::where('is_active', true)->get();
        return view('admin.purchase-receipts.edit', compact('receipt', 'purchaseOrders', 'suppliers', 'products'));
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

        // Update receipt items
        $receipt->items()->delete();
        foreach ($request->items as $item) {
            $receipt->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        return redirect()->route('admin.purchase-receipts.index')
            ->with('success', 'تم تحديث إيصال الاستلام بنجاح');
    }

    public function destroy(PurchaseReceipt $receipt)
    {
        $receipt->delete();

        return redirect()->route('admin.purchase-receipts.index')
            ->with('success', 'تم حذف إيصال الاستلام بنجاح');
    }
}
