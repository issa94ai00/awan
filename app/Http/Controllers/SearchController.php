<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([
                'products' => [],
                'categories' => [],
                'suggestions' => [],
            ]);
        }

        // Search products
        $products = Product::query()
            ->where('is_active', 1)
            ->whereSearch(['name_ar', 'name_en', 'description_ar', 'brand', 'model'], $query)
            ->with('category:id,name_ar,slug')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name_ar' => $product->name_ar,
                    'name_en' => $product->name_en,
                    'price' => $product->price,
                    'image_main' => $product->image_main,
                    'category' => $product->category ? [
                        'name_ar' => $product->category->name_ar,
                        'slug' => $product->category->slug,
                    ] : null,
                    'type' => 'product',
                    'url' => route('product.show', $product),
                ];
            });

        // Search categories
        $categories = Category::query()
            ->where('is_active', 1)
            ->whereSearch(['name_ar', 'name_en', 'description'], $query)
            ->limit(3)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name_ar' => $category->name_ar,
                    'name_en' => $category->name_en,
                    'icon' => $category->icon,
                    'product_count' => $category->product_count,
                    'type' => 'category',
                    'url' => route('category.show', $category),
                ];
            });

        // Generate suggestions based on matching words
        $suggestions = [];
        if ($products->isNotEmpty()) {
            $suggestions = $products->take(3)->pluck('name_ar')->toArray();
        }

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'suggestions' => $suggestions,
            'query' => $query,
            'total_results' => $products->count() + $categories->count(),
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 1) {
            return response()->json(['suggestions' => []]);
        }

        // Get product name suggestions
        $productSuggestions = Product::query()
            ->where('is_active', 1)
            ->whereSearch(['name_ar'], $query)
            ->limit(5)
            ->pluck('name_ar')
            ->toArray();

        // Get category name suggestions
        $categorySuggestions = Category::query()
            ->where('is_active', 1)
            ->whereSearch(['name_ar'], $query)
            ->limit(3)
            ->pluck('name_ar')
            ->toArray();

        $suggestions = array_merge($productSuggestions, $categorySuggestions);

        return response()->json([
            'suggestions' => array_slice($suggestions, 0, 5),
        ]);
    }
}
