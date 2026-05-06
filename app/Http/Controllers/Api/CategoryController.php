<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Get all active categories
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', 1)
            ->withCount(['products as product_count' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => CategoryResource::collection($categories)
        ]);
    }

    /**
     * Get category details by slug
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        abort_unless((int) ($category->is_active ?? 0) === 1, 404);

        $category->loadCount(['products as product_count' => function ($query) {
            $query->where('is_active', 1);
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully',
            'data' => new CategoryResource($category)
        ]);
    }

    /**
     * Get products for a specific category
     */
    public function products(Request $request, Category $category): JsonResponse
    {
        abort_unless((int) ($category->is_active ?? 0) === 1, 404);

        $products = $category->products()
            ->where('is_active', 1)
            ->with('category')
            ->orderByDesc('created_at')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Category products retrieved successfully',
            'data' => [
                'category' => new CategoryResource($category),
                'products' => ProductResource::collection($products->items()),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'has_more_pages' => $products->hasMorePages(),
                ]
            ]
        ]);
    }
}
