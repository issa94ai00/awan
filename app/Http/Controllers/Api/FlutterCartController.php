<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The mobile app's cart. A cart line lives on the user's Cart (cart_items has
 * a cart_id, not a user_id), priced when it is added, as the web cart does.
 */
class FlutterCartController extends Controller
{
    private function cartFor(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }

    public function index(Request $request)
    {
        $cart = $this->cartFor($request);
        $cartItems = $cart->items()->with('product', 'variant')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $cartItems,
                'total' => $cartItems->sum(fn ($item) => $item->subtotal),
                'count' => $cartItems->count(),
            ],
        ]);
    }

    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product = Product::findOrFail($request->product_id);

        $variant = null;
        if ($request->filled('variant_id')) {
            $variant = ProductVariant::where('product_id', $product->id)->find($request->variant_id);
            if (! $variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'المتغير المحدد لا يتبع هذا المنتج',
                ], 422);
            }
            $variant->setRelation('product', $product);
        }

        $cart = $this->cartFor($request);

        $cartItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        if ($cartItem) {
            $cartItem->update(['quantity' => $cartItem->quantity + $request->quantity]);
        } else {
            $cartItem = $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $request->quantity,
                'price' => $variant ? $variant->sellingPrice() : ($product->price ?? 0),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart',
            'data' => ['cart_item' => $cartItem->load('product')],
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_item_id' => 'required|exists:cart_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cartItem = $this->cartFor($request)->items()->find($request->cart_item_id);

        if (! $cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found',
            ], 404);
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated',
            'data' => ['cart_item' => $cartItem->load('product')],
        ]);
    }

    public function remove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_item_id' => 'required|exists:cart_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cartItem = $this->cartFor($request)->items()->find($request->cart_item_id);

        if (! $cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found',
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart',
        ]);
    }

    public function clear(Request $request)
    {
        CartItem::whereIn('cart_id', Cart::where('user_id', $request->user()->id)->pluck('id'))->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared',
        ]);
    }
}
