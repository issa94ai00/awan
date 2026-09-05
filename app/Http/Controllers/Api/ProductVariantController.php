<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
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
            ]);

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
     * Quick partial update for a variant's price/size/color/material/stock —
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
            ]);

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
        try {
            $variant = ProductVariant::findOrFail($variant);
            $variant->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المتغير بنجاح',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في حذف المتغير',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
