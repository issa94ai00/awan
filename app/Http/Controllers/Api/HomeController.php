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
        \Log::info('API HomeController index method called');
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
     * Get featured / new arrivals / best sellers products with pagination
     */
    public function featuredProducts(Request $request): JsonResponse
    {
        $type = $request->get('type', 'featured');
        $query = \App\Models\Product::where('is_active', 1)
            ->where('in_stock', 1)
            ->with('category');

        switch ($type) {
            case 'new':
                $query->where('created_at', '>=', now()->subDays(30));
                break;
            case 'best':
                $query->orderByDesc('views_count');
                break;
            case 'featured':
            default:
                $query->where('is_featured', 1);
                $query->orderByDesc('created_at');
                break;
        }

        if ($type !== 'best') {
            $query->orderByDesc('created_at');
        }

        $products = $query->paginate(12);

        $message = match ($type) {
            'new' => 'New arrivals retrieved successfully',
            'best' => 'Best sellers retrieved successfully',
            default => 'Featured products retrieved successfully',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'type' => $type,
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
