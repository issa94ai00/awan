<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\CategoryResource;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    /**
     * Comprehensive search across products and categories
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'message' => 'Search query too short',
                'data' => [
                    'products' => [],
                    'categories' => [],
                    'suggestions' => [],
                    'total_results' => 0,
                ]
            ]);
        }

        $searchTerm = '%' . $query . '%';

        // Search products
        $products = Product::query()
            ->where('is_active', 1)
            ->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'like', $searchTerm)
                  ->orWhere('name_en', 'like', $searchTerm)
                  ->orWhere('description_ar', 'like', $searchTerm)
                  ->orWhere('brand', 'like', $searchTerm)
                  ->orWhere('model', 'like', $searchTerm);
            })
            ->with('category:id,name_ar,slug')
            ->limit(20)
            ->get();

        // Search categories
        $categories = Category::query()
            ->where('is_active', 1)
            ->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'like', $searchTerm)
                  ->orWhere('name_en', 'like', $searchTerm)
                  ->orWhere('description', 'like', $searchTerm);
            })
            ->withCount(['products as product_count' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->limit(10)
            ->get();

        // Generate suggestions based on matching words
        $suggestions = [];
        if ($products->isNotEmpty()) {
            $suggestions = $products->take(5)->pluck('name_ar')->toArray();
        }
        if ($categories->isNotEmpty()) {
            $categorySuggestions = $categories->take(3)->pluck('name_ar')->toArray();
            $suggestions = array_merge($suggestions, $categorySuggestions);
        }
        $suggestions = array_unique($suggestions);
        $suggestions = array_slice($suggestions, 0, 5);

        return response()->json([
            'success' => true,
            'message' => 'Search results retrieved successfully',
            'data' => [
                'products' => ProductResource::collection($products),
                'categories' => CategoryResource::collection($categories),
                'suggestions' => $suggestions,
                'query' => $query,
                'total_results' => $products->count() + $categories->count(),
            ]
        ]);
    }

    /**
     * Get search suggestions as user types
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 1) {
            return response()->json([
                'success' => true,
                'message' => 'No suggestions available',
                'data' => ['suggestions' => []]
            ]);
        }

        $searchTerm = '%' . $query . '%';

        // Get product name suggestions
        $productSuggestions = Product::query()
            ->where('is_active', 1)
            ->where('name_ar', 'like', $searchTerm)
            ->limit(5)
            ->pluck('name_ar')
            ->toArray();

        // Get category name suggestions
        $categorySuggestions = Category::query()
            ->where('is_active', 1)
            ->where('name_ar', 'like', $searchTerm)
            ->limit(3)
            ->pluck('name_ar')
            ->toArray();

        $suggestions = array_merge($productSuggestions, $categorySuggestions);
        $suggestions = array_unique($suggestions);
        $suggestions = array_slice($suggestions, 0, 5);

        return response()->json([
            'success' => true,
            'message' => 'Suggestions retrieved successfully',
            'data' => ['suggestions' => $suggestions]
        ]);
    }
}
