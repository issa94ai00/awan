<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use Illuminate\Http\Request;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'creator']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $query->where('order_number', 'like', '%' . $request->search . '%')
                ->orWhereHas('customer', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
        }

        $salesOrders = $query->latest()->paginate(20);
        $customers = Customer::all();

        return view('admin.sales-orders.index', compact('salesOrders', 'customers'));
    }

    public function create()
    {
        $customers = Customer::all();
        $products = Product::where('is_active', true)->get();
        return view('admin.sales-orders.create', compact('customers', 'products'));
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

        // Calculate totals
        $subtotal = 0;
        foreach ($request->items as $item) {
            $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
            $subtotal += $itemTotal;
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal - ($validated['discount'] ?? 0) + ($validated['tax'] ?? 0);

        $salesOrder = SalesOrder::create($validated);

        // Create sales order items
        foreach ($request->items as $item) {
            $salesOrder->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
            ]);
        }

        return redirect()->route('admin.sales-orders.index')
            ->with('success', 'تم إنشاء طلب البيع بنجاح');
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'creator', 'items.product', 'quote']);
        return view('admin.sales-orders.show', compact('salesOrder'));
    }

    public function edit(SalesOrder $salesOrder)
    {
        $salesOrder->load('items.product');
        $customers = Customer::all();
        $products = Product::where('is_active', true)->get();
        return view('admin.sales-orders.edit', compact('salesOrder', 'customers', 'products'));
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

        // Calculate totals
        $subtotal = 0;
        foreach ($request->items as $item) {
            $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
            $subtotal += $itemTotal;
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal - ($validated['discount'] ?? 0) + ($validated['tax'] ?? 0);

        $salesOrder->update($validated);

        // Update sales order items
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

        return redirect()->route('admin.sales-orders.index')
            ->with('success', 'تم تحديث طلب البيع بنجاح');
    }

    public function destroy(SalesOrder $salesOrder)
    {
        $salesOrder->delete();

        return redirect()->route('admin.sales-orders.index')
            ->with('success', 'تم حذف طلب البيع بنجاح');
    }

    public function convertToInvoice(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== SalesOrder::STATUS_CONFIRMED) {
            return redirect()->back()
                ->with('error', 'يمكن تحويل طلبات البيع المؤكدة فقط إلى فواتير');
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

        // Copy sales order items to invoice items
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

        // Update customer balance
        $salesOrder->customer->updateBalance($salesOrder->total);

        return redirect()->route('admin.sales.invoices')
            ->with('success', 'تم تحويل طلب البيع إلى فاتورة بنجاح');
    }
}
