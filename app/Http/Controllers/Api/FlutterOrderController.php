<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CartItem;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FlutterOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::where('user_id', $request->user()->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->with(['items', 'shippingAddress'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function show(Request $request, $id)
    {
        $order = Order::with(['items', 'shippingAddress', 'paymentMethod'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address_id' => 'required|exists:addresses,id',
            'payment_method_type' => 'required|in:card,cash,wallet',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $cartItems = CartItem::with('product')->where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $subtotal = $cartItems->sum(function ($item) {
                return $item->total;
            });
            $shippingCost = 10;
            $tax = $subtotal * 0.1;
            $total = $subtotal + $shippingCost + $tax;

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'tax' => $tax,
                'total' => $total,
                'shipping_address_id' => $request->shipping_address_id,
                'payment_method_id' => $request->payment_method_id,
                'payment_method_type' => $request->payment_method_type,
                'notes' => $request->notes,
            ]);

            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'product_title' => $cartItem->product->name,
                    'product_image' => $cartItem->product->image_main,
                    'product_brand' => $cartItem->product->sku ?? '',
                    'price' => $cartItem->product->price,
                    'price_after_discount' => null,
                    'quantity' => $cartItem->quantity,
                    'size' => $cartItem->size,
                    'color' => $cartItem->color,
                ]);

                // Goes through the inventory service rather than writing
                // stock_quantity directly: a bare model write leaves no movement
                // record and never reaches warehouse_inventory, so the WMS and
                // the product total drift apart on every mobile order.
                app(\App\Services\Inventory\InventoryService::class)->issue(
                    $cartItem->product_id,
                    (int) $cartItem->quantity,
                    null,
                    [
                        'key' => 'order:' . $order->id . ':product:' . $cartItem->product_id,
                        'source' => 'mobile_order',
                        'reference' => (string) $order->id,
                        'reason' => 'طلب تطبيق #' . $order->id,
                        // The order is already committed; a shortfall is recorded
                        // rather than allowed to fail the checkout.
                        'allow_negative' => true,
                    ]
                );
            }

            CartItem::where('user_id', $user->id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => ['order' => $order->load('items')]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if (!in_array($order->status, ['pending', 'processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be canceled'
            ], 400);
        }

        $order->status = 'canceled';
        $order->save();

        // Put the goods back the same way they left — through the service, so
        // the return is an auditable movement instead of a silent edit.
        $inventory = app(\App\Services\Inventory\InventoryService::class);

        foreach ($order->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            $inventory->receive(
                $item->product_id,
                (int) $item->quantity,
                null,
                [
                    'key' => 'order:' . $order->id . ':cancel:product:' . $item->product_id,
                    'source' => 'mobile_order',
                    'reference' => (string) $order->id,
                    'reason' => 'إلغاء طلب تطبيق #' . $order->id,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order canceled successfully'
        ]);
    }

    public function returnOrder(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status !== 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Order must be delivered to return'
            ], 400);
        }

        $order->status = 'returned';
        $order->notes = $request->reason;
        $order->save();

        $order->user->walletTransactions()->create([
            'type' => 'credit',
            'amount' => $order->total,
            'description' => 'Refund for returned order #' . $order->order_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Return request submitted'
        ]);
    }
}
