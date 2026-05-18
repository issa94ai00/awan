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
        $categoriesQuery = Category::query()
            ->where('is_active', 1)
            ->withCount(['products as product_count' => function ($query) {
                $query->where('is_active', 1);
            }])
            // include a single sample active product to help clients show thumbnails
            ->with(['products' => function ($q) {
                $q->where('is_active', 1)
                  ->orderByDesc('created_at')
                  ->limit(1);
            }])
            ->orderBy('sort_order');

        // Optionally return only categories that have active products
        if ($request->boolean('only_with_products')) {
            $categoriesQuery->having('product_count', '>', 0);
        }

        $categories = $categoriesQuery->get();

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

        $perPage = (int) $request->get('per_page', 12);
        $perPage = $perPage > 0 ? min($perPage, 100) : 12;

        $productsQuery = $category->products()
            ->where('is_active', 1)
            ->with('category')
            ->orderByDesc('created_at');

        // Allow optional simple search within the category
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $productsQuery->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'like', $searchTerm)
                  ->orWhere('name_en', 'like', $searchTerm)
                  ->orWhere('brand', 'like', $searchTerm)
                  ->orWhere('model', 'like', $searchTerm);
            });
        }

        $products = $productsQuery->paginate($perPage);

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
