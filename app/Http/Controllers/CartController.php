<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Session;

class CartController extends Controller
{
    public function getCart()
    {
        $sessionId = Session::getId();
        $userId = auth()->id();

        $cart = Cart::where(function ($query) use ($sessionId, $userId) {
            $query->where('session_id', $sessionId);
            if ($userId) {
                $query->orWhere('user_id', $userId);
            }
        })->with('items.product', 'items.variant')->first();

        if (!$cart) {
            $cart = Cart::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
            ]);
        }

        return $cart;
    }

    public function index()
    {
        $cart = $this->getCart();
        return view('cart', compact('cart'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getCart();
        $product = Product::findOrFail($request->product_id);

        // A variant card in the store adds that variant, at its own price.
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

        $cartItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $cartItem->quantity + $request->quantity,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $request->quantity,
                'price' => $variant ? $variant->sellingPrice() : ($product->price ?? 0),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المنتج إلى السلة',
            'cart_count' => $cart->total_items,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getCart();
        $cartItem = $cart->items()->findOrFail($id);

        $cartItem->update([
            'quantity' => $request->quantity,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الكمية',
            'subtotal' => $cartItem->subtotal,
            'total' => $cart->total,
            'cart_count' => $cart->total_items,
        ]);
    }

    public function remove($id)
    {
        $cart = $this->getCart();
        $cartItem = $cart->items()->findOrFail($id);
        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المنتج من السلة',
            'total' => $cart->total,
            'cart_count' => $cart->total_items,
        ]);
    }

    public function clear()
    {
        $cart = $this->getCart();
        $cart->items()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تفريغ السلة',
        ]);
    }

    public function getCartCount()
    {
        $cart = $this->getCart();
        return response()->json([
            'count' => $cart->total_items,
        ]);
    }

    public function getCartData()
    {
        $cart = $this->getCart();
        $cart->load('items.product.category', 'items.variant');
        return response()->json([
            'success' => true,
            'cart' => [
                'id' => $cart->id,
                'session_id' => $cart->session_id,
                'user_id' => $cart->user_id,
                'total' => $cart->total,
                'total_items' => $cart->total_items,
                'items' => $cart->items->map(function ($item) {
                    // A variant line reads as the variant: its name, code and
                    // stock, the way the store listed it.
                    $variant = $item->variant;

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->product_variant_id,
                        'variant_label' => $variant?->label,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name_ar' => $variant ? $variant->displayName($item->product->name_ar) : $item->product->name_ar,
                            'name_en' => $variant && $item->product->name_en ? $variant->displayName($item->product->name_en) : $item->product->name_en,
                            'slug' => $item->product->slug,
                            'sku' => $variant?->sku ?: $item->product->sku,
                            'price' => $variant ? $item->price : $item->product->price,
                            'sale_price' => $variant ? null : $item->product->sale_price,
                            'show_price' => $item->product->show_price,
                            'image_main' => $item->product->image_main_url,
                            'in_stock' => $item->product->in_stock,
                            'stock_quantity' => $variant ? $variant->stock_quantity : $item->product->stock_quantity,
                            'category' => $item->product->category ? [
                                'id' => $item->product->category->id,
                                'name_ar' => $item->product->category->name_ar,
                                'slug' => $item->product->category->slug,
                            ] : null,
                        ] : null
                    ];
                })
            ]
        ]);
    }
}
