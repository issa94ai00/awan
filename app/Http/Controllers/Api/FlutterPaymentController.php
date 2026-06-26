<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FlutterPaymentController extends Controller
{
    public function index(Request $request)
    {
        $paymentMethods = PaymentMethod::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['payment_methods' => $paymentMethods]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_number' => 'required|string|min:16|max:19',
            'cardholder_name' => 'required|string',
            'expiry_date' => 'required|string|regex:/^\d{2}\/\d{2}$/',
            'card_type' => 'required|in:visa,mastercard,amex',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if ($request->is_default) {
            PaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $cardNumberLast4 = substr($request->card_number, -4);

        $paymentMethod = PaymentMethod::create([
            'user_id' => $user->id,
            'card_number_last4' => $cardNumberLast4,
            'cardholder_name' => $request->cardholder_name,
            'expiry_date' => $request->expiry_date,
            'card_type' => $request->card_type,
            'is_default' => $request->is_default ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment method added successfully',
            'data' => ['payment_method' => $paymentMethod]
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $paymentMethod = PaymentMethod::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        $paymentMethod->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully'
        ]);
    }
}
