<?php

namespace App\Http\Controllers\Admin;

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
        $query = Quote::with(['customer', 'creator']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $query->where('quote_number', 'like', '%' . $request->search . '%')
                ->orWhereHas('customer', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
        }

        $quotes = $query->latest()->paginate(20);
        $customers = Customer::all();

        return view('admin.quotes.index', compact('quotes', 'customers'));
    }

    public function create()
    {
        $customers = Customer::all();
        $products = Product::where('is_active', true)->get();
        return view('admin.quotes.create', compact('customers', 'products'));
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

        // Calculate totals
        $subtotal = 0;
        foreach ($request->items as $item) {
            $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
            $subtotal += $itemTotal;
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal - ($validated['discount'] ?? 0) + ($validated['tax'] ?? 0);

        $quote = Quote::create($validated);

        // Create quote items
        foreach ($request->items as $item) {
            $quote->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
            ]);
        }

        return redirect()->route('admin.quotes.index')
            ->with('success', 'تم إنشاء عرض السعر بنجاح');
    }

    public function show(Quote $quote)
    {
        $quote->load(['customer', 'creator', 'items.product']);
        return view('admin.quotes.show', compact('quote'));
    }

    public function edit(Quote $quote)
    {
        $quote->load('items.product');
        $customers = Customer::all();
        $products = Product::where('is_active', true)->get();
        return view('admin.quotes.edit', compact('quote', 'customers', 'products'));
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

        // Calculate totals
        $subtotal = 0;
        foreach ($request->items as $item) {
            $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
            $subtotal += $itemTotal;
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal - ($validated['discount'] ?? 0) + ($validated['tax'] ?? 0);

        $quote->update($validated);

        // Update quote items
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

        return redirect()->route('admin.quotes.index')
            ->with('success', 'تم تحديث عرض السعر بنجاح');
    }

    public function destroy(Quote $quote)
    {
        $quote->delete();

        return redirect()->route('admin.quotes.index')
            ->with('success', 'تم حذف عرض السعر بنجاح');
    }

    public function convertToSalesOrder(Quote $quote)
    {
        if ($quote->status !== Quote::STATUS_ACCEPTED) {
            return redirect()->back()
                ->with('error', 'يمكن تحويل عروض الأسعار المقبولة فقط إلى طلبات بيع');
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

        // Copy quote items to sales order items
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

        return redirect()->route('admin.sales-orders.show', $salesOrder)
            ->with('success', 'تم تحويل عرض السعر إلى طلب بيع بنجاح');
    }
}
