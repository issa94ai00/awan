<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
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
        })->with('items.product')->first();

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
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getCart();
        $product = Product::findOrFail($request->product_id);

        $cartItem = $cart->items()->where('product_id', $request->product_id)->first();

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $cartItem->quantity + $request->quantity,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'price' => $product->price ?? 0,
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
        $cart->load('items.product.category');
        return response()->json([
            'success' => true,
            'cart' => [
                'id' => $cart->id,
                'session_id' => $cart->session_id,
                'user_id' => $cart->user_id,
                'total' => $cart->total,
                'total_items' => $cart->total_items,
                'items' => $cart->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name_ar' => $item->product->name_ar,
                            'name_en' => $item->product->name_en,
                            'slug' => $item->product->slug,
                            'price' => $item->product->price,
                            'sale_price' => $item->product->sale_price,
                            'show_price' => $item->product->show_price,
                            'image_main' => $item->product->image_main_url,
                            'in_stock' => $item->product->in_stock,
                            'stock_quantity' => $item->product->stock_quantity,
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
