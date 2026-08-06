<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FlutterWishlistController extends Controller
{
    public function index(Request $request)
    {
        $wishlistItems = WishlistItem::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['wishlist_items' => $wishlistItems]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $wishlistItem = WishlistItem::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'product_id' => $request->product_id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Item added to wishlist',
            'data' => ['wishlist_item' => $wishlistItem->load('product')]
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $wishlistItem = WishlistItem::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$wishlistItem) {
            return response()->json([
                'success' => false,
                'message' => 'Wishlist item not found'
            ], 404);
        }

        $wishlistItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from wishlist'
        ]);
    }
}
