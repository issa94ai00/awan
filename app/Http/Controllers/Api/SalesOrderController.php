<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\Invoice;
use Illuminate\Http\Request;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'creator', 'items.product']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        $salesOrders = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Sales orders retrieved successfully',
            'data' => [
                'sales_orders' => $salesOrders->items(),
                'pagination' => [
                    'current_page' => $salesOrders->currentPage(),
                    'last_page' => $salesOrders->lastPage(),
                    'per_page' => $salesOrders->perPage(),
                    'total' => $salesOrders->total(),
                    'has_more_pages' => $salesOrders->hasMorePages(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_date' => 'nullable|date',
            'expected_delivery' => 'nullable|date|after:order_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        $validated['order_number'] = 'SO-' . str_pad(SalesOrder::count() + 1, 6, '0', STR_PAD_LEFT);
        $validated['status'] = SalesOrder::STATUS_PENDING;
        $validated['created_by'] = auth()->id();

        $subtotal = 0;
        foreach ($request->items as $item) {
            $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
            $subtotal += $itemTotal;
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal - ($validated['discount'] ?? 0) + ($validated['tax'] ?? 0);

        $salesOrder = SalesOrder::create($validated);

        foreach ($request->items as $item) {
            $salesOrder->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
            ]);
        }

        $salesOrder->load(['customer', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء طلب البيع بنجاح',
            'data' => $salesOrder
        ], 201);
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'creator', 'items.product', 'quote']);

        return response()->json([
            'success' => true,
            'message' => 'Sales order retrieved successfully',
            'data' => $salesOrder
        ]);
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'order_date' => 'nullable|date',
            'expected_delivery' => 'nullable|date|after:order_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        $subtotal = 0;
        foreach ($request->items as $item) {
            $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
            $subtotal += $itemTotal;
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal - ($validated['discount'] ?? 0) + ($validated['tax'] ?? 0);

        $salesOrder->update($validated);

        $salesOrder->items()->delete();
        foreach ($request->items as $item) {
            $salesOrder->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
            ]);
        }

        $salesOrder->load(['customer', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث طلب البيع بنجاح',
            'data' => $salesOrder
        ]);
    }

    public function destroy(SalesOrder $salesOrder)
    {
        $salesOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف طلب البيع بنجاح',
            'data' => null
        ]);
    }

    public function convertToInvoice(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== SalesOrder::STATUS_CONFIRMED) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن تحويل طلبات البيع المؤكدة فقط إلى فواتير',
                'data' => null
            ], 400);
        }

        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT),
            'customer_id' => $salesOrder->customer_id,
            'sales_order_id' => $salesOrder->id,
            'customer_name' => $salesOrder->customer->name,
            'customer_email' => $salesOrder->customer->email,
            'customer_phone' => $salesOrder->customer->phone,
            'subtotal' => $salesOrder->subtotal,
            'tax' => $salesOrder->tax,
            'discount' => $salesOrder->discount,
            'total' => $salesOrder->total,
            'paid_amount' => 0,
            'due_amount' => $salesOrder->total,
            'status' => Invoice::STATUS_PENDING,
            'notes' => $salesOrder->notes,
            'created_by' => auth()->id(),
        ]);

        foreach ($salesOrder->items as $item) {
            $invoice->items()->create([
                'product_id' => $item->product_id,
                'description' => $item->product->name_ar,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax' => $item->tax,
                'total' => $item->total,
            ]);
        }

        $salesOrder->customer->updateBalance($salesOrder->total);

        $invoice->load(['customer', 'salesOrder', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحويل طلب البيع إلى فاتورة بنجاح',
            'data' => $invoice
        ], 201);
    }
}
