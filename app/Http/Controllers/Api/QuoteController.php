<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $query = Quote::with(['customer', 'creator', 'items.product']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        $quotes = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Quotes retrieved successfully',
            'data' => [
                'quotes' => $quotes->items(),
                'pagination' => [
                    'current_page' => $quotes->currentPage(),
                    'last_page' => $quotes->lastPage(),
                    'per_page' => $quotes->perPage(),
                    'total' => $quotes->total(),
                    'has_more_pages' => $quotes->hasMorePages(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'valid_until' => 'nullable|date|after:today',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:2000',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        $validated['quote_number'] = 'QT-' . str_pad(Quote::count() + 1, 6, '0', STR_PAD_LEFT);
        $validated['status'] = Quote::STATUS_DRAFT;
        $validated['created_by'] = auth()->id();

        $subtotal = 0;
        foreach ($request->items as $item) {
            $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
            $subtotal += $itemTotal;
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal - ($validated['discount'] ?? 0) + ($validated['tax'] ?? 0);

        $quote = Quote::create($validated);

        foreach ($request->items as $item) {
            $quote->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
            ]);
        }

        $quote->load(['customer', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء عرض السعر بنجاح',
            'data' => $quote
        ], 201);
    }

    public function show(Quote $quote)
    {
        $quote->load(['customer', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Quote retrieved successfully',
            'data' => $quote
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'valid_until' => 'nullable|date|after:today',
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:2000',
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

        $quote->update($validated);

        $quote->items()->delete();
        foreach ($request->items as $item) {
            $quote->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
            ]);
        }

        $quote->load(['customer', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث عرض السعر بنجاح',
            'data' => $quote
        ]);
    }

    public function destroy(Quote $quote)
    {
        $quote->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف عرض السعر بنجاح',
            'data' => null
        ]);
    }

    public function convertToSalesOrder(Quote $quote)
    {
        if ($quote->status !== Quote::STATUS_ACCEPTED) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن تحويل عروض الأسعار المقبولة فقط إلى طلبات بيع',
                'data' => null
            ], 400);
        }

        $salesOrder = SalesOrder::create([
            'order_number' => 'SO-' . str_pad(SalesOrder::count() + 1, 6, '0', STR_PAD_LEFT),
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'status' => SalesOrder::STATUS_PENDING,
            'order_date' => now(),
            'subtotal' => $quote->subtotal,
            'tax' => $quote->tax,
            'discount' => $quote->discount,
            'total' => $quote->total,
            'notes' => $quote->notes,
            'created_by' => auth()->id(),
        ]);

        foreach ($quote->items as $item) {
            $salesOrder->items()->create([
                'product_id' => $item->product_id,
                'description' => $item->product->name_ar,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax' => $item->tax,
            ]);
        }

        $salesOrder->load(['customer', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحويل عرض السعر إلى طلب بيع بنجاح',
            'data' => $salesOrder
        ], 201);
    }
}
