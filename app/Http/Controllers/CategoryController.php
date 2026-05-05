<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()
            ->where('is_active', 1)
            ->withCount(['products as product_count' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->orderBy('sort_order')
            ->get();

        return view('categories', compact('categories'));
    }

    public function show(Request $request, Category $category)
    {
        abort_unless((int) ($category->is_active ?? 0) === 1, 404);

        $products = Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', 1)
            ->with('category')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('category', compact('category', 'products'));
    }
}
