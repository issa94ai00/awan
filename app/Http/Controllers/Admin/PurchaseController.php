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
        $suppliers = Supplier::orderBy('name')->paginate(20);

        return view('admin.purchases.suppliers', compact('suppliers'));
    }

    public function orders(Request $request)
    {
        $orders = PurchaseOrder::with('supplier')->latest()->paginate(20);

        return view('admin.purchases.orders', compact('orders'));
    }
}
