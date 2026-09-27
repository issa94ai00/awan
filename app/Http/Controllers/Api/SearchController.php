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
        $query = trim((string) $request->get('q', ''));
        $perPage = min(max((int) $request->get('per_page', 12), 1), 48);

        // Characters, not bytes: two Arabic letters are four bytes, so a
        // single letter used to pass as a two-character query.
        if (mb_strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'message' => 'Search query too short',
                'data' => [
                    'products' => [],
                    'categories' => [],
                    'suggestions' => [],
                    'total_results' => 0,
                    'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0, 'has_more_pages' => false],
                ]
            ]);
        }

        $searchTerm = '%' . $query . '%';
        $prefix = $query . '%';

        // Listed the way category pages list them — a row per size or colour,
        // each at its own price — so a search result and a category card for
        // the same thing look and link alike. Paginated: it used to stop at
        // the first twenty matches and call that the result count.
        $productsQuery = Product::query()
            ->withVariantRows()
            ->where('products.is_active', 1)
            ->where(function ($q) use ($searchTerm) {
                $q->where('products.name_ar', 'like', $searchTerm)
                  ->orWhere('products.name_en', 'like', $searchTerm)
                  ->orWhere('products.description_ar', 'like', $searchTerm)
                  ->orWhere('products.brand', 'like', $searchTerm)
                  ->orWhere('products.model', 'like', $searchTerm)
                  ->orWhere('products.sku', 'like', $searchTerm)
                  ->orWhere('pv.sku', 'like', $searchTerm);
            })
            ->with('category:id,name_ar,name_en,slug');

        if ($request->boolean('in_stock')) {
            $productsQuery->storefrontInStock(true);
        }

        // Unless another order is asked for, names that start with the query
        // come before names that merely contain it.
        if (! $request->filled('sort') || $request->input('sort') === 'relevance') {
            $productsQuery->orderByRaw(
                'CASE WHEN products.name_ar LIKE ? OR products.name_en LIKE ? THEN 0 ELSE 1 END',
                [$prefix, $prefix]
            );
        }
        $productsQuery->storefrontSort($request->input('sort'), true, $request->input('lang'));

        $products = $productsQuery->paginate($perPage);

        // Search categories
        $categories = Category::query()
            ->where('is_active', 1)
            ->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'like', $searchTerm)
                  ->orWhere('name_en', 'like', $searchTerm)
                  ->orWhere('description', 'like', $searchTerm);
            })
            ->withProductCount()
            ->limit(10)
            ->get();

        // Generate suggestions based on matching words
        $suggestions = collect($products->items())->take(5)->pluck('name_ar')
            ->merge($categories->take(3)->pluck('name_ar'))
            ->unique()
            ->take(5)
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Search results retrieved successfully',
            'data' => [
                'products' => ProductResource::collection($products->items()),
                'categories' => CategoryResource::collection($categories),
                'suggestions' => $suggestions,
                'query' => $query,
                'total_results' => $products->total() + $categories->count(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'has_more_pages' => $products->hasMorePages(),
                ],
            ]
        ]);
    }

    /**
     * Get search suggestions as user types — returns product objects for the navbar autocomplete.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 1) {
            return response()->json([
                'success' => true,
                'message' => 'No suggestions available',
                'data' => []
            ]);
        }

        $searchTerm = '%' . $query . '%';

        // Return product objects so the navbar can show thumbnails + links
        $products = Product::query()
            ->where('is_active', 1)
            ->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'like', $searchTerm)
                  ->orWhere('name_en', 'like', $searchTerm)
                  ->orWhere('brand', 'like', $searchTerm)
                  ->orWhere('model', 'like', $searchTerm);
            })
            ->with('category:id,name_ar,slug')
            ->limit(6)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Suggestions retrieved successfully',
            'data' => ProductResource::collection($products)
        ]);
    }
}
