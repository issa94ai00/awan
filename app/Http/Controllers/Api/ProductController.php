<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Support\ImageStore;
use App\Support\ProductIdentifiers;
use App\Models\Product;
use App\Models\Category;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;
use App\Services\ProductExcelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    /**
     * Build the shared filtered product query.
     */
    private function baseQuery(Request $request): Builder
    {
        $query = Product::query()
            ->with('category');

        if ($request->boolean('with_variants')) {
            $query->with('variants');
        }

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

        // Filter by category_id (single value or array, for multi-classification
        // filtering) or category_slug
        if ($request->filled('category_id')) {
            $query->whereIn('category_id', (array) $request->category_id);
        } elseif ($request->filled('category_slug')) {
            $cat = Category::where('slug', $request->category_slug)->first();
            if ($cat) {
                $query->where('category_id', $cat->id);
            } else {
                // No such category -> empty result
                return $query->whereRaw('1 = 0');
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

        // Search by name, brand, model, SKU or barcode — a purchasing screen
        // looking up a product by its code needs this as much as a shopper
        // searching by name does.
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'like', $searchTerm)
                  ->orWhere('name_en', 'like', $searchTerm)
                  ->orWhere('brand', 'like', $searchTerm)
                  ->orWhere('model', 'like', $searchTerm)
                  ->orWhere('sku', 'like', $searchTerm)
                  ->orWhere('barcode', 'like', $searchTerm);
            });
        }

        return $query;
    }

    /**
     * Get all products with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request);

        // Per-page with max cap
        $perPage = (int) $request->get('per_page', 12);
        $perPage = $perPage > 0 ? min($perPage, 100) : 12;

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

        $isAdmin = $request->is('*admin*') || $request->routeIs('api.admin.*');

        // Public frontend must not reach inactive products; the admin editor
        // opens them all the time (a disabled product still needs editing).
        if (! $isAdmin) {
            abort_unless((int) ($product->is_active ?? 0) === 1, 404);
        }

        $product->load('category');

        // The admin product form's Variants tab needs the variant rows; the
        // public product page doesn't (it relies on the base-product fields).
        if ($isAdmin) {
            $product->load('variants');
        }

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
            'name_en' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
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
            'image_main' => 'nullable|string|max:2048',
            'image_gallery' => 'nullable|array',
            'image_gallery.*' => 'nullable|string|max:2048',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string'
        ]);

        // Slug and SKU are the catalogue's plumbing, not something an admin
        // should have to invent: 1,600 of the products already in the table
        // carry no SKU at all, and demanding one to add the next product only
        // teaches people to type junk. The slug is derived when it is left
        // blank; a blank SKU stays blank.
        $validated['slug'] = ProductIdentifiers::uniqueSlug(
            $this->blankToNull($validated['slug'] ?? null) ?? ($validated['name_ar'] ?? null),
            $validated['name_en'] ?? null
        );
        $validated['sku'] = $this->blankToNull($validated['sku'] ?? null);
        $validated['name_en'] = $this->blankToNull($validated['name_en'] ?? null);

        $validated['image_main'] = image_path($validated['image_main'] ?? null);
        $validated['image_gallery'] = array_key_exists('image_gallery', $validated)
            ? json_encode($this->normalizeGallery($validated['image_gallery']))
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
            'name_en' => 'sometimes|nullable|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255|unique:products,slug,' . $product->id,
            'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|required|string|max:3',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'warehouse_id' => 'sometimes|nullable|integer|exists:warehouses,id',
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
            'sku' => 'sometimes|nullable|string|max:255|unique:products,sku,' . $product->id,
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
            'image_main' => 'nullable|string|max:2048',
            'image_gallery' => 'nullable|array',
            'image_gallery.*' => 'nullable|string|max:2048',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string'
        ]);

        // Saving an existing product used to fail on fields nobody had touched:
        // `sku` was "sometimes|required", so the 1,600 products stored without
        // one could not be edited at all, and a blank slug meant a 422 rather
        // than "keep the slug it already has". Neither is the admin's problem.
        if (array_key_exists('slug', $validated)) {
            if ($this->blankToNull($validated['slug']) === null) {
                // A product's slug is its public URL. Clearing the field means
                // "no change", never "mint a new address for this page".
                unset($validated['slug']);
            } else {
                $validated['slug'] = ProductIdentifiers::uniqueSlug(
                    $validated['slug'],
                    $validated['name_ar'] ?? $product->name_ar,
                    $product->id
                );
            }
        }

        foreach (['sku', 'name_en'] as $optional) {
            if (array_key_exists($optional, $validated)) {
                $validated[$optional] = $this->blankToNull($validated[$optional]);
            }
        }

        // The form is handed image_url()'s absolute URLs and posts them back
        // unchanged, so an untouched image round-tripped into the row as
        // "https://host/images_items/x.svg" — host baked in, and no longer the
        // path any other reader (the model accessors, the import, the sitemap)
        // expects. Both fields are folded back to their stored relative form.
        if (array_key_exists('image_main', $validated)) {
            $validated['image_main'] = image_path($validated['image_main']);
        }

        if (array_key_exists('image_gallery', $validated)) {
            $validated['image_gallery'] = json_encode($this->normalizeGallery($validated['image_gallery']));
        }

        // What the product shows now, read before the update overwrites it, so
        // the files it stops showing can be swept once the new state is saved.
        $imagesBefore = ImageStore::paths([$product->image_main, $product->image_gallery]);

        // Editing the stock figure on the product form is a stock count, not a
        // field edit: writing stock_quantity straight through would leave the
        // warehouse rows the WMS reads untouched and no record of who changed
        // what. The difference is booked as an adjustment instead, and the
        // service keeps the product total in step.
        $countedQuantity = $validated['stock_quantity'] ?? null;
        $targetWarehouseId = $validated['warehouse_id'] ?? null;
        unset($validated['stock_quantity'], $validated['warehouse_id']);

        // A caller that names a warehouse (the products screen's quick edit,
        // which shows the count for one warehouse at a time) is recounting
        // that warehouse specifically, not the company-wide total — so the
        // difference is against what that warehouse row currently holds, not
        // against `product.stock_quantity` summed across all of them.
        $difference = null;

        if ($countedQuantity !== null) {
            if ($targetWarehouseId) {
                $row = WarehouseInventory::where('warehouse_id', $targetWarehouseId)
                    ->where('product_id', $product->id)
                    ->whereNull('product_variant_id')
                    ->first();

                $currentAtWarehouse = $row ? (int) $row->quantity : 0;
                $reservedAtWarehouse = $row ? (int) $row->reserved_quantity : 0;

                // Caught here, before anything is written, rather than left to
                // the database's negative-balance trigger: that fires mid
                // transaction and would otherwise leave the other field edits
                // on this same request saved while the count silently failed.
                if ((int) $countedQuantity < $reservedAtWarehouse) {
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(
                            'الكمية الجديدة (%d) أقل من المحجوز لطلبات قائمة في هذا المستودع (%d). عالج الطلبات أولاً أو صحّح الجرد في مستودع آخر.',
                            $countedQuantity,
                            $reservedAtWarehouse
                        ),
                        'data' => null,
                    ], 422);
                }

                $difference = (int) $countedQuantity - $currentAtWarehouse;
            } else {
                $difference = (int) $countedQuantity - (int) $product->stock_quantity;
            }
        }

        try {
            DB::transaction(function () use ($product, $validated, $difference, $targetWarehouseId) {
                $product->update($validated);

                if ($difference !== null && $difference !== 0) {
                    app(InventoryService::class)->adjust(
                        $product->id,
                        $difference,
                        $targetWarehouseId,
                        [
                            'source' => 'stock_count',
                            'reason' => 'تعديل الرصيد من بطاقة المنتج',
                            'reference' => $product->sku ?? ('#' . $product->id),
                        ]
                    );
                }
            });
        } catch (\App\Exceptions\ClosedPeriodException $e) {
            // Has its own render() with the closed-period detail the screen
            // needs to point the user at it; catching it into the generic
            // handler below would flatten that down to a bare message.
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        $product->refresh();

        // Pruned after the commit, never inside it: the row is the record of
        // what the product shows, and a file that refuses to delete must not
        // roll back an otherwise good save.
        ImageStore::forgetReplaced($imagesBefore, [$product->image_main, $product->image_gallery]);

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
    /**
     * Hand the form the next free code, for admins who do want one.
     *
     * Sequential numbering has to come from the server: the browser cannot
     * know which codes are taken.
     */
    public function nextSku(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ['sku' => ProductIdentifiers::nextSku()],
        ]);
    }

    /**
     * An emptied text field means "no value", not the empty string — which
     * would otherwise collide with the next emptied field on a unique index.
     */
    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Fold a posted gallery back to the relative paths the column stores.
     *
     * Blanks (an upload that failed but stayed in the picker's list) and
     * duplicates (the same file dropped twice, or a gallery entry that is also
     * the main image) are dropped rather than written out as empty <img> src
     * values on the storefront.
     */
    private function normalizeGallery(?array $gallery): array
    {
        return collect($gallery ?? [])
            ->map(function ($image) {
                if (is_array($image)) {
                    $image = $image['url'] ?? $image['path'] ?? null;
                }

                return is_string($image) ? image_path($image) : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function destroy($product): JsonResponse
    {
        if (! $product instanceof Product) {
            $product = Product::where('id', $product)
                ->orWhere('slug', $product)
                ->firstOrFail();
        }

        $images = ImageStore::paths([$product->image_main, $product->image_gallery]);

        $product->delete();

        ImageStore::forget($images);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Export filtered products to an xlsx file (Admin)
     */
    public function export(Request $request, ProductExcelService $excel): Response
    {
        $products = $this->baseQuery($request)
            ->limit(50000)
            ->get();

        $binary = $excel->exportProducts($products);

        $filename = 'products-' . now()->format('Y-m-d') . '.xlsx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Import products from an uploaded xlsx file (Admin)
     */
    public function import(Request $request, ProductExcelService $excel): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            return response()->json([
                'success' => false,
                'message' => 'The file must be an xlsx file.',
                'data' => null,
            ], 422);
        }

        $result = $excel->importFile($file);

        return response()->json([
            'success' => true,
            'message' => 'Products imported successfully',
            'data' => $result,
        ]);
    }
}
