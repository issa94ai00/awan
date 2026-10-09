<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    /**
     * The home page: the shop's sections, a row of products and the
     * catalogue's size.
     *
     * Sections are the top-level ones with something in them, each with a
     * picture (see Category::attachThumbnails) — the first ten categories in
     * sort order used to mix sections with one-product subcategories.
     *
     * Products are the featured ones; with none featured, a daily pick of
     * in-stock products with real photos, so the row is never empty.
     * `products_source` (featured | picks) says which the page got.
     */
    public function index(Request $request): JsonResponse
    {
        $all = Category::where('is_active', 1)
            ->withProductCount()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        Category::attachThumbnails($all);

        $sections = $all
            ->filter(fn (Category $category) => ! $category->parent_id && (int) $category->product_count > 0)
            ->values();

        $products = Product::where('is_featured', 1)
            ->where('is_active', 1)
            ->where('in_stock', 1)
            ->with('category')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();
        $source = 'featured';

        if ($products->isEmpty()) {
            $source = 'picks';
            // Seeded by the date: a different dozen each day, the same one all
            // day long, so the row does not reshuffle on every visit.
            // Shuffled in PHP: RAND(seed) is MySQL's alone.
            $ids = Product::query()
                ->where('is_active', 1)
                ->where('in_stock', 1)
                ->whereNotNull('image_main')
                ->where('image_main', 'not like', '%images_items/generic/%')
                ->pluck('id')
                ->shuffle((int) now()->format('Ymd'))
                ->take(12);

            $products = Product::whereIn('id', $ids)->with('category')->get()
                ->sortBy(fn (Product $product) => $ids->search($product->id))
                ->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Home data retrieved successfully',
            'data' => [
                'categories' => CategoryResource::collection($sections->take(8)),
                'featured_products' => ProductResource::collection($products),
                'products_source' => $source,
                'stats' => [
                    // Counted the way the listings count: each variant is an entry.
                    'products' => Product::query()->withVariantRows()->where('products.is_active', 1)->count(),
                    'sections' => $sections->count(),
                ],
            ],
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
