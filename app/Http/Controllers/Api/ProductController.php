<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Category;
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

        // Per-page with max cap
        $perPage = (int) $request->get('per_page', 12);
        $perPage = $perPage > 0 ? min($perPage, 100) : 12;

        // Filter by category_id or category_slug
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        } elseif ($request->filled('category_slug')) {
            $cat = Category::where('slug', $request->category_slug)->first();
            if ($cat) {
                $query->where('category_id', $cat->id);
            } else {
                // No such category -> empty result
                $products = collect([]);
                return response()->json([
                    'success' => true,
                    'message' => 'Products retrieved successfully',
                    'data' => [
                        'products' => [],
                        'pagination' => [
                            'current_page' => 1,
                            'last_page' => 0,
                            'per_page' => $perPage,
                            'total' => 0,
                            'has_more_pages' => false,
                        ],
                        'filters' => $request->only(['category_id','category_slug','featured','in_stock','search','min_price','max_price','sort_by','sort_order','per_page'])
                    ]
                ]);
            }
        }

        // Filter by featured
        if ($request->boolean('featured')) {
            $query->where('is_featured', 1);
        }

        // Filter by stock availability
        if ($request->boolean('in_stock')) {
            $query->where('in_stock', 1);
        }

        // Price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // Search by name, brand, or model
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'like', $searchTerm)
                  ->orWhere('name_en', 'like', $searchTerm)
                  ->orWhere('brand', 'like', $searchTerm)
                  ->orWhere('model', 'like', $searchTerm);
            });
        }

        // Sort options — sanitize inputs
        $allowedSorts = ['name_ar', 'name_en', 'price', 'created_at'];
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $products = $query->paginate($perPage);

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
                'filters' => array_merge($request->only(['category_id','category_slug','featured','in_stock','search','min_price','max_price','sort_by','sort_order']), ['per_page' => $perPage])
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

    /**
     * Store a new product (Admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:3',
            'stock_quantity' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'image_main' => 'nullable|string',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string'
        ]);

        $product = Product::create($validated);
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => new ProductResource($product)
        ], 201);
    }

    /**
     * Update a product (Admin)
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name_ar' => 'sometimes|required|string|max:255',
            'name_en' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:products,slug,' . $product->id,
            'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|required|string|max:3',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'image_main' => 'nullable|string',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string'
        ]);

        $product->update($validated);
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Delete a product (Admin)
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
            'data' => null
        ]);
    }
}
