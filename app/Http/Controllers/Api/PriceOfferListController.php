<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceOfferList;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceOfferListController extends Controller
{
    /**
     * Return the authenticated user's saved price-offer selection lists.
     * Each list carries its item count so the UI can show how many products
     * it holds.
     */
    public function index(Request $request): JsonResponse
    {
        $lists = PriceOfferList::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Price-offer lists retrieved successfully',
            'data' => $lists,
        ]);
    }

    /**
     * Return a single list including its item keys (`p-{productId}` /
     * `v-{variantId}`) so the frontend can re-apply the saved selection.
     */
    public function show(Request $request, $list): JsonResponse
    {
        $list = PriceOfferList::with('items')
            ->where('user_id', $request->user()->id)
            ->findOrFail($list);

        $itemKeys = $list->items
            ->map(fn ($item) => $item->product_variant_id
                ? "v-{$item->product_variant_id}"
                : ($item->product_id ? "p-{$item->product_id}" : null))
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Price-offer list retrieved successfully',
            'data' => [
                'id' => $list->id,
                'name_ar' => $list->name_ar,
                'name_en' => $list->name_en,
                'item_keys' => $itemKeys,
            ],
        ]);
    }

    /**
     * Create a named list from an array of item keys (`p-{productId}` or
     * `v-{variantId}`). If items are omitted the list is created empty and can
     * be filled later.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:190',
                'items' => 'sometimes|array',
                'items.*' => 'string|max:190',
            ]);

            $list = DB::transaction(function () use ($request, $validated) {
                $list = PriceOfferList::create([
                    'user_id' => $request->user()->id,
                    'name_ar' => $validated['name'],
                    'name_en' => $validated['name'],
                ]);
                $this->syncItems($list, $validated['items'] ?? []);

                return $list;
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء القائمة بنجاح',
                'data' => $list->loadCount('items'),
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
                'message' => 'خطأ في إنشاء القائمة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rename a list and/or replace its items. Only the owning user may do so.
     */
    public function update(Request $request, $list): JsonResponse
    {
        try {
            $list = PriceOfferList::where('user_id', $request->user()->id)->findOrFail($list);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:190',
                'items' => 'sometimes|array',
                'items.*' => 'string|max:190',
            ]);

            if (isset($validated['name'])) {
                $list->update([
                    'name_ar' => $validated['name'],
                    'name_en' => $validated['name'],
                ]);
            }

            if (array_key_exists('items', $validated)) {
                DB::transaction(function () use ($list, $validated) {
                    $this->syncItems($list, $validated['items']);
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث القائمة بنجاح',
                'data' => $list->loadCount('items'),
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
                'message' => 'خطأ في تحديث القائمة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a list. Only the owning user may delete it.
     */
    public function destroy(Request $request, $list): JsonResponse
    {
        try {
            $list = PriceOfferList::where('user_id', $request->user()->id)->findOrFail($list);
            $list->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف القائمة بنجاح',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في حذف القائمة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Replace a list's items with the parsed set of product/variant ids.
     * Keys arrive as `p-{productId}` or `v-{variantId}`.
     */
    private function syncItems(PriceOfferList $list, array $keys): void
    {
        $list->items()->delete();

        $productIds = [];
        $variantIds = [];
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $parts = explode('-', $key, 2);
            $type = $parts[0] ?? '';
            $id = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;
            if ($id === null) {
                continue;
            }
            if ($type === 'p') {
                $productIds[] = $id;
            } elseif ($type === 'v') {
                $variantIds[] = $id;
            }
        }

        // Only persist ids that still exist, so a renamed/removed product can
        // never break the save with a foreign-key error.
        $validProductIds = $productIds ? Product::whereIn('id', $productIds)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        $validVariantIds = $variantIds ? ProductVariant::whereIn('id', $variantIds)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        foreach ($validProductIds as $pid) {
            $list->items()->create(['product_id' => $pid]);
        }
        foreach ($validVariantIds as $vid) {
            $list->items()->create(['product_variant_id' => $vid]);
        }
    }
}