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
            ->with('category');

        // Check if admin route
        $isAdmin = $request->routeIs('api.admin.*') || $request->is('*admin*');

        if ($isAdmin) {
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
        } else {
            // Public frontend only gets active products
            $query->where('is_active', 1);
        }

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
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 0,
                    'per_page' => $perPage,
                    'total' => 0,
                    'has_more_pages' => false
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

        // Price range (accept both min_price/price_min and max_price/price_max)
        $minPrice = $request->get('min_price') ?? $request->get('price_min');
        if ($minPrice !== null && $minPrice !== '') {
            $query->where('price', '>=', (float) $minPrice);
        }
        $maxPrice = $request->get('max_price') ?? $request->get('price_max');
        if ($maxPrice !== null && $maxPrice !== '') {
            $query->where('price', '<=', (float) $maxPrice);
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
        $sortBy = 'created_at';
        $sortOrder = 'desc';
        $useRawSort = false;
        $rawSortQuery = '';

        if ($request->filled('sort')) {
            $sortVal = $request->get('sort');
            switch ($sortVal) {
                case 'price_asc':
                    $useRawSort = true;
                    $rawSortQuery = 'CASE WHEN sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price THEN sale_price ELSE price END asc';
                    break;
                case 'price_desc':
                    $useRawSort = true;
                    $rawSortQuery = 'CASE WHEN sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price THEN sale_price ELSE price END desc';
                    break;
                case 'name_asc':
                    $sortBy = 'name_ar';
                    $sortOrder = 'asc';
                    break;
                case 'name_desc':
                    $sortBy = 'name_ar';
                    $sortOrder = 'desc';
                    break;
                case 'latest':
                default:
                    $sortBy = 'created_at';
                    $sortOrder = 'desc';
                    break;
            }
        } else {
            $allowedSorts = ['name_ar', 'name_en', 'price', 'created_at'];
            $sortByInput = $request->get('sort_by', 'created_at');
            $sortOrderInput = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
            if (in_array($sortByInput, $allowedSorts, true)) {
                if ($sortByInput === 'price') {
                    $useRawSort = true;
                    $rawSortQuery = 'CASE WHEN sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price THEN sale_price ELSE price END ' . $sortOrderInput;
                } else {
                    $sortBy = $sortByInput;
                    $sortOrder = $sortOrderInput;
                }
            }
        }

        if ($useRawSort) {
            $query->orderByRaw($rawSortQuery);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => ProductResource::collection($products->items()),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'has_more_pages' => $products->hasMorePages(),
            ]
        ]);
    }

    /**
     * Get product details by slug
     */
    public function show(Request $request, $product): JsonResponse
    {
        if (! $product instanceof Product) {
            $product = Product::where('id', $product)
                ->orWhere('slug', $product)
                ->firstOrFail();
        }

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
    public function related(Request $request, $product): JsonResponse
    {
        if (! $product instanceof Product) {
            $product = Product::where('id', $product)
                ->orWhere('slug', $product)
                ->firstOrFail();
        }

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
            'max_stock' => 'nullable|integer|min:0',
            'reorder_point' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:100',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'short_description_ar' => 'nullable|string|max:500',
            'short_description_en' => 'nullable|string|max:500',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'barcode' => 'nullable|string|max:255',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'taxable' => 'boolean',
            'unit' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'show_price' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'in_stock' => 'boolean',
            'image_main' => 'nullable|string',
            'image_gallery' => 'nullable|array',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string'
        ]);

        $validated['image_gallery'] = isset($validated['image_gallery'])
            ? json_encode($validated['image_gallery'])
            : null;

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
    public function update(Request $request, $product): JsonResponse
    {
        if (! $product instanceof Product) {
            $product = Product::where('id', $product)
                ->orWhere('slug', $product)
                ->firstOrFail();
        }

        $validated = $request->validate([
            'name_ar' => 'sometimes|required|string|max:255',
            'name_en' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:products,slug,' . $product->id,
            'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|required|string|max:3',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'min_stock' => 'sometimes|nullable|integer|min:0',
            'max_stock' => 'sometimes|nullable|integer|min:0',
            'reorder_point' => 'sometimes|nullable|integer|min:0',
            'weight' => 'sometimes|nullable|numeric|min:0',
            'length' => 'sometimes|nullable|numeric|min:0',
            'width' => 'sometimes|nullable|numeric|min:0',
            'height' => 'sometimes|nullable|numeric|min:0',
            'color' => 'sometimes|nullable|string|max:100',
            'size' => 'sometimes|nullable|string|max:100',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'short_description_ar' => 'sometimes|nullable|string|max:500',
            'short_description_en' => 'sometimes|nullable|string|max:500',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'sku' => 'sometimes|required|string|max:255|unique:products,sku,' . $product->id,
            'barcode' => 'sometimes|nullable|string|max:255',
            'cost_price' => 'sometimes|nullable|numeric|min:0',
            'tax_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'taxable' => 'sometimes|boolean',
            'unit' => 'sometimes|nullable|string|max:50',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'show_price' => 'boolean',
            'sort_order' => 'sometimes|nullable|integer|min:0',
            'in_stock' => 'boolean',
            'image_main' => 'nullable|string',
            'image_gallery' => 'nullable|array',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string'
        ]);

        if (isset($validated['image_gallery'])) {
            $validated['image_gallery'] = json_encode($validated['image_gallery']);
        }

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
    public function destroy($product): JsonResponse
    {
        if (! $product instanceof Product) {
            $product = Product::where('id', $product)
                ->orWhere('slug', $product)
                ->firstOrFail();
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
            'data' => null
        ]);
    }
}
