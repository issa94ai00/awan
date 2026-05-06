<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Get all products with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('is_active', 1)
            ->with('category');

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by featured
        if ($request->boolean('featured')) {
            $query->where('is_featured', 1);
        }

        // Filter by stock availability
        if ($request->boolean('in_stock')) {
            $query->where('in_stock', 1);
        }

        // Search by name, brand, or model
        if ($request->has('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'like', $searchTerm)
                  ->orWhere('name_en', 'like', $searchTerm)
                  ->orWhere('brand', 'like', $searchTerm)
                  ->orWhere('model', 'like', $searchTerm);
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['name_ar', 'name_en', 'price', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $products = $query->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => [
                'products' => ProductResource::collection($products->items()),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'has_more_pages' => $products->hasMorePages(),
                ],
                'filters' => [
                    'category_id' => $request->get('category_id'),
                    'featured' => $request->boolean('featured'),
                    'in_stock' => $request->boolean('in_stock'),
                    'search' => $request->get('search'),
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ]
            ]
        ]);
    }

    /**
     * Get product details by slug
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        abort_unless((int) ($product->is_active ?? 0) === 1, 404);

        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Get related products for a specific product
     */
    public function related(Request $request, Product $product): JsonResponse
    {
        abort_unless((int) ($product->is_active ?? 0) === 1, 404);

        $related_products = Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', 1)
            ->with('category')
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Related products retrieved successfully',
            'data' => ProductResource::collection($related_products)
        ]);
    }
}
