<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index()
    {
        $suppliersCount = Supplier::count();
        $ordersCount = PurchaseOrder::count();
        $pendingOrders = PurchaseOrder::where('status', 'pending')->count();

        return view('admin.purchases.index', compact('suppliersCount', 'ordersCount', 'pendingOrders'));
    }

    public function suppliers(Request $request)
    {
        $query = Supplier::query();

        if ($request->has('search') && $request->search) {
            $query->whereSearch(['name', 'email', 'phone', 'company'], $request->search);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $suppliers = $query->orderBy('name')->paginate(20);

        return view('admin.purchases.suppliers', compact('suppliers'));
    }

    public function orders(Request $request)
    {
        $query = PurchaseOrder::with('supplier');

        if ($request->has('search') && $request->search) {
            $query->whereSearch(['order_number', 'supplier.name'], $request->search);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(20);

        return view('admin.purchases.orders', compact('orders'));
    }
}
