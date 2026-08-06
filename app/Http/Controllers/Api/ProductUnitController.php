<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductUnit;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProductUnitController extends Controller
{
    /**
     * List all units for a product
     */
    public function index(Request $request, $productId): JsonResponse
    {
        try {
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنتج غير موجود',
                ], 404);
            }

            $units = $product->units()->orderBy('is_default', 'desc')->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الوحدات بنجاح',
                'data' => $units,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الوحدات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new unit for a product
     */
    public function store(Request $request, $productId): JsonResponse
    {
        try {
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنتج غير موجود',
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'barcode' => 'nullable|string|max:255',
                'base_unit_multiplier' => 'required|numeric|min:0.01',
                'price_multiplier' => 'required|numeric|min:0.01',
                'is_default' => 'nullable|boolean',
            ], [
                'name.required' => 'اسم الوحدة مطلوب',
                'base_unit_multiplier.required' => 'معامل الوحدة الأساسية مطلوب',
                'base_unit_multiplier.min' => 'معامل الوحدة الأساسية يجب أن يكون أكبر من 0',
                'price_multiplier.required' => 'معامل السعر مطلوب',
                'price_multiplier.min' => 'معامل السعر يجب أن يكون أكبر من 0',
            ]);

            // If this is set as default, unset other defaults
            if (!empty($validated['is_default']) && $validated['is_default']) {
                $product->units()->update(['is_default' => false]);
            }

            $unit = $product->units()->create($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الوحدة بنجاح',
                'data' => $unit,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في إنشاء الوحدة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a unit
     */
    public function update(Request $request, $productId, $unitId): JsonResponse
    {
        try {
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنتج غير موجود',
                ], 404);
            }

            $unit = ProductUnit::where('id', $unitId)->where('product_id', $productId)->first();
            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'الوحدة غير موجودة',
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'barcode' => 'nullable|string|max:255',
                'base_unit_multiplier' => 'required|numeric|min:0.01',
                'price_multiplier' => 'required|numeric|min:0.01',
                'is_default' => 'nullable|boolean',
            ], [
                'name.required' => 'اسم الوحدة مطلوب',
                'base_unit_multiplier.required' => 'معامل الوحدة الأساسية مطلوب',
                'base_unit_multiplier.min' => 'معامل الوحدة الأساسية يجب أن يكون أكبر من 0',
                'price_multiplier.required' => 'معامل السعر مطلوب',
                'price_multiplier.min' => 'معامل السعر يجب أن يكون أكبر من 0',
            ]);

            // If this is set as default, unset other defaults
            if (!empty($validated['is_default']) && $validated['is_default']) {
                $product->units()->where('id', '!=', $unit->id)->update(['is_default' => false]);
            }

            $unit->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الوحدة بنجاح',
                'data' => $unit->fresh(),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحديث الوحدة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a unit
     */
    public function destroy($productId, $unitId): JsonResponse
    {
        try {
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنتج غير موجود',
                ], 404);
            }

            $unit = ProductUnit::where('id', $unitId)->where('product_id', $productId)->first();
            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'الوحدة غير موجودة',
                ], 404);
            }

            // Don't allow deleting the default unit if it's the only one
            if ($unit->is_default && $product->units()->count() === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف الوحدة الأساسية إذا كانت الوحيدة',
                ], 422);
            }

            $unit->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الوحدة بنجاح',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في حذف الوحدة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search units by barcode
     */
    public function searchByBarcode(Request $request): JsonResponse
    {
        try {
            $barcode = $request->input('barcode');

            if (!$barcode) {
                return response()->json([
                    'success' => false,
                    'message' => 'الباركود مطلوب',
                ], 422);
            }

            $unit = ProductUnit::with('product')
                ->where('barcode', $barcode)
                ->first();

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على وحدة بهذا الباركود',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم العثور على الوحدة',
                'data' => [
                    'unit' => $unit,
                    'product' => $unit->product,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البحث عن الباركود',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
