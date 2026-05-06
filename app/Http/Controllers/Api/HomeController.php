<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    /**
     * Get home page data including categories and featured products
     */
    public function index(Request $request): JsonResponse
    {
        $categories = \App\Models\Category::where('is_active', 1)
            ->withCount(['products as product_count' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->orderBy('sort_order')
            ->limit(10)
            ->get();

        $featured_products = \App\Models\Product::where('is_featured', 1)
            ->where('is_active', 1)
            ->where('in_stock', 1)
            ->with('category')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Home data retrieved successfully',
            'data' => [
                'categories' => CategoryResource::collection($categories),
                'featured_products' => ProductResource::collection($featured_products),
            ]
        ]);
    }

    /**
     * Get featured products with pagination
     */
    public function featuredProducts(Request $request): JsonResponse
    {
        $featured_products = \App\Models\Product::where('is_featured', 1)
            ->where('is_active', 1)
            ->where('in_stock', 1)
            ->with('category')
            ->orderByDesc('created_at')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Featured products retrieved successfully',
            'data' => [
                'products' => ProductResource::collection($featured_products->items()),
                'pagination' => [
                    'current_page' => $featured_products->currentPage(),
                    'last_page' => $featured_products->lastPage(),
                    'per_page' => $featured_products->perPage(),
                    'total' => $featured_products->total(),
                    'has_more_pages' => $featured_products->hasMorePages(),
                ]
            ]
        ]);
    }
}
