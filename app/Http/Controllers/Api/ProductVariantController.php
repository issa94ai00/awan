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
     * Quick partial update for a variant's price/size/color/material/stock —
     * used by the price-offer list's inline row editors.
     */
    public function update(Request $request, $variant): JsonResponse
    {
        try {
            $variant = ProductVariant::findOrFail($variant);

            $validated = $request->validate([
                'size' => 'sometimes|nullable|string|max:100',
                'color' => 'sometimes|nullable|string|max:100',
                'material' => 'sometimes|nullable|string|max:100',
                'price' => 'sometimes|required|numeric|min:0',
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
}
