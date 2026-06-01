<?php

namespace App\Http\Controllers\Admin;

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

        if ($request->has('search') && $request->search) {
            $query->where('order_number', 'like', '%' . $request->search . '%')
                ->orWhereHas('product', function ($q) use ($request) {
                    $q->where('name_ar', 'like', '%' . $request->search . '%')
                        ->orWhere('name_en', 'like', '%' . $request->search . '%');
                });
        }

        $productionOrders = $query->latest()->paginate(20);
        $products = Product::where('is_active', true)->get();

        return view('admin.production.index', compact('productionOrders', 'products'));
    }

    public function create()
    {
        $products = Product::where('is_active', true)->get();
        return view('admin.production.create', compact('products'));
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

        ProductionOrder::create($validated);

        return redirect()->route('admin.production.index')
            ->with('success', 'تم إنشاء أمر الإنتاج بنجاح');
    }

    public function show(ProductionOrder $productionOrder)
    {
        $productionOrder->load(['product', 'creator']);
        return view('admin.production.show', compact('productionOrder'));
    }

    public function edit(ProductionOrder $productionOrder)
    {
        $products = Product::where('is_active', true)->get();
        return view('admin.production.edit', compact('productionOrder', 'products'));
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

        return redirect()->route('admin.production.index')
            ->with('success', 'تم تحديث أمر الإنتاج بنجاح');
    }

    public function destroy(ProductionOrder $productionOrder)
    {
        $productionOrder->delete();

        return redirect()->route('admin.production.index')
            ->with('success', 'تم حذف أمر الإنتاج بنجاح');
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

        return redirect()->back()
            ->with('success', 'تم تحديث حالة أمر الإنتاج بنجاح');
    }
}
