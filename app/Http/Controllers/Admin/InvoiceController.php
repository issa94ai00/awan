<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with(['customer', 'creator', 'items.product']);

        if ($request->has('search') && $request->search) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', $search)
                    ->orWhereHas('customer', function ($qCustomer) use ($search) {
                        $qCustomer->where('name', 'like', $search);
                    });
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $invoices = $query->latest()->paginate(20);

        return view('admin.sales.invoices', compact('invoices'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.sales.invoice-create', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method' => 'nullable|in:cash,card,transfer',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $productIds = collect($validated['items'])->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $subtotal = 0;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            $product = $products->get($item['product_id']);
            $unitPrice = (float) $item['unit_price'];
            $quantity = (int) $item['quantity'];
            $totalPrice = $unitPrice * $quantity;

            $itemsData[] = [
                'product_id' => $item['product_id'],
                'product_name' => $product->name_ar ?? $product->name_en ?? 'منتج غير معروف',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
            ];

            $subtotal += $totalPrice;
        }

        $tax = (float) ($validated['tax'] ?? 0);
        $discount = (float) ($validated['discount'] ?? 0);
        $total = max(0, $subtotal + $tax - $discount);

        $invoice = new Invoice();
        $invoiceNumber = $invoice->generateInvoiceNumber();

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'customer_id' => $validated['customer_id'] ?? null,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'due_amount' => $total,
            'payment_method' => $validated['payment_method'] ?? Invoice::PAYMENT_CASH,
            'status' => Invoice::STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        foreach ($itemsData as $itemData) {
            $itemData['invoice_id'] = $invoice->id;
            InvoiceItem::create($itemData);
        }

        return redirect()->route('admin.sales.invoices')
            ->with('success', 'تم إنشاء الفاتورة بنجاح');
    }

    public function searchProducts(Request $request)
    {
        $query = $request->input('q', '');

        $products = Product::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name_ar', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get(['id', 'name_ar', 'name_en', 'price', 'sku', 'stock_quantity', 'unit', 'tax_rate', 'taxable']);

        return response()->json($products);
    }
}
