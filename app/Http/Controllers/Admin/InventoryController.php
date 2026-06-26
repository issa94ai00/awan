<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('category')
            ->orderBy('name_ar')
            ->paginate(20);

        $stockCount = StockMovement::sum('quantity');

        return view('admin.inventory.index', compact('products', 'stockCount'));
    }

    public function movements(Request $request)
    {
        $movements = StockMovement::with('product')
            ->latest()
            ->paginate(20);

        return view('admin.inventory.movements', compact('movements'));
    }
}
