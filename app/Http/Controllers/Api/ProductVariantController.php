<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    /**
     * A variant's own details: an ordered list of label/value rows. The
     * value is what matters; a row may leave its label blank.
     *
     * @return array<string, string>
     */
    private static function specsRules(): array
    {
        return [
            'specs' => 'sometimes|nullable|array|max:30',
            'specs.*' => 'array',
            'specs.*.label' => 'nullable|string|max:100',
            'specs.*.value' => 'nullable|string|max:255',
        ];
    }

    /**
     * Ensure this variant's combination of size, color and material is unique within the product,
     * and barcode is unique when provided.
     *
     * @throws ValidationException
     */
    private function assertUniqueConstraints(int $productId, ?string $size, ?string $color, ?string $material, ?string $barcode = null, ?int $ignoreVariantId = null): void
    {
        $size = trim((string) $size) ?: null;
        $color = trim((string) $color) ?: null;
        $material = trim((string) $material) ?: null;

        $query = ProductVariant::where('product_id', $productId);
        if ($ignoreVariantId) {
            $query->where('id', '!=', $ignoreVariantId);
        }

        $size === null ? $query->whereNull('size') : $query->where('size', $size);
        $color === null ? $query->whereNull('color') : $query->where('color', $color);
        $material === null ? $query->whereNull('material') : $query->where('material', $material);

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'size' => ['يوجد بالفعل متغيّر لهذا المنتج بنفس الخيارات (المقاس، اللون، الخامة).'],
            ]);
        }

        $barcode = trim((string) $barcode) ?: null;
        if ($barcode !== null) {
            $barcodeConflict = ProductVariant::where('barcode', $barcode)
                ->when($ignoreVariantId, fn ($q) => $q->where('id', '!=', $ignoreVariantId))
                ->exists() || \App\Models\Product::where('barcode', $barcode)
                ->when($productId, fn ($q) => $q->where('id', '!=', $productId))
                ->exists();

            if ($barcodeConflict) {
                throw ValidationException::withMessages([
                    'barcode' => ['الباركود مستخدم بالفعل لصنف أو متغيّر آخر.'],
                ]);
            }
        }
    }

    /**
     * Get a specific variant by ID.
     */
    public function show(Request $request, $variant): JsonResponse
    {
        $variant = ProductVariant::with(['product' => fn ($q) => $q->with('category')])->findOrFail($variant);

        $seesCost = \App\Http\Resources\ProductResource::viewerSeesCost($request);

        $productData = $variant->product ? [
            'id' => $variant->product->id,
            'name_ar' => $variant->product->name_ar,
            'name_en' => $variant->product->name_en,
            'slug' => $variant->product->slug,
            'sku' => $variant->product->sku,
            'barcode' => $variant->product->barcode,
            'price' => $variant->product->price,
            'sale_price' => $variant->product->sale_price,
            'category_id' => $variant->product->category_id,
            'category' => $variant->product->category ? [
                'id' => $variant->product->category->id,
                'name_ar' => $variant->product->category->name_ar,
                'name_en' => $variant->product->category->name_en,
                'slug' => $variant->product->category->slug,
            ] : null,
        ] : null;

        $data = [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'size' => $variant->size,
            'color' => $variant->color,
            'material' => $variant->material,
            'price' => $variant->price,
            'stock_quantity' => $variant->stock_quantity,
            'specs' => $variant->specs ?? [],
            'label' => $variant->label,
            'product' => $productData,
        ];

        if ($seesCost) {
            $data['cost_price'] = $variant->cost_price;
        }

        return response()->json([
            'success' => true,
            'message' => 'Variant retrieved successfully',
            'data' => $data,
        ]);
    }

    /**
     * Create a new variant under a product.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'sku' => 'required|string|max:255|unique:product_variants,sku',
                'barcode' => 'nullable|string|max:255',
                'size' => 'nullable|string|max:100',
                'color' => 'nullable|string|max:100',
                'material' => 'nullable|string|max:100',
                'price' => 'required|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'stock_quantity' => 'required|integer|min:0',
                ...self::specsRules(),
            ]);

            $this->assertUniqueConstraints(
                (int) $validated['product_id'],
                $validated['size'] ?? null,
                $validated['color'] ?? null,
                $validated['material'] ?? null,
                $validated['barcode'] ?? null
            );

            if (array_key_exists('specs', $validated)) {
                $validated['specs'] = ProductVariant::normaliseSpecs($validated['specs']);
            }

            $variant = ProductVariant::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المتغير بنجاح',
                'data' => $variant->fresh(),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في إنشاء المتغير',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Quick partial update for a variant's price/size/color/material/stock/details —
     * used by the price-offer list's inline row editors and the product form.
     */
    public function update(Request $request, $variant): JsonResponse
    {
        try {
            $variant = ProductVariant::findOrFail($variant);

            $validated = $request->validate([
                'sku' => 'sometimes|nullable|string|max:255|unique:product_variants,sku,' . $variant->id,
                'barcode' => 'sometimes|nullable|string|max:255',
                'size' => 'sometimes|nullable|string|max:100',
                'color' => 'sometimes|nullable|string|max:100',
                'material' => 'sometimes|nullable|string|max:100',
                'price' => 'sometimes|required|numeric|min:0',
                'cost_price' => 'sometimes|nullable|numeric|min:0',
                'stock_quantity' => 'sometimes|required|integer|min:0',
                ...self::specsRules(),
            ]);

            $size = array_key_exists('size', $validated) ? $validated['size'] : $variant->size;
            $color = array_key_exists('color', $validated) ? $validated['color'] : $variant->color;
            $material = array_key_exists('material', $validated) ? $validated['material'] : $variant->material;
            $barcode = array_key_exists('barcode', $validated) ? $validated['barcode'] : $variant->barcode;

            $this->assertUniqueConstraints(
                (int) $variant->product_id,
                $size,
                $color,
                $material,
                $barcode,
                $variant->id
            );

            if (array_key_exists('specs', $validated)) {
                $validated['specs'] = ProductVariant::normaliseSpecs($validated['specs']);
            }

            $variant->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المتغير بنجاح',
                'data' => $variant->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحديث المتغير',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a variant. Warehouse inventory rows referencing it cascade off;
     * stock movement/order records keep the variant id so the history is intact.
     */
    public function destroy($variant): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variant);

        // warehouse_inventory cascades off a deleted variant, so deleting one
        // that is still on a shelf silently drops the stock with it and the
        // count on the floor stops matching the count in the system. Whoever
        // holds the goods has to issue or adjust them first — deliberately,
        // through the screen that records why.
        $onHand = (float) DB::table('warehouse_inventory')
            ->where('product_variant_id', $variant->id)
            ->sum('quantity');

        if ($onHand > 0) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف متغيّر ما زال في المخزون (المتوفر '.\App\Support\NumberFormat::quantity($onHand).'). أخرج الكمية أو سوِّها أولاً.',
                'data' => null,
            ], 422);
        }

        try {
            $variant->delete();
        } catch (QueryException $e) {
            // Picking lists, batches and serial numbers hold the variant with
            // ON DELETE NO ACTION, so the database refuses. That refusal used
            // to surface as a 500 carrying raw SQL; it is an ordinary, and
            // correct, answer to the request.
            return response()->json([
                'success' => false,
                'message' => 'هذا المتغيّر مرتبط بسجلات أخرى (أوامر تجهيز أو دفعات أو أرقام تسلسلية)، فلا يمكن حذفه.',
                'data' => null,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المتغير بنجاح',
            'data' => null,
        ]);
    }
}
