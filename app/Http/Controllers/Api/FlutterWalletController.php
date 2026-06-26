<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FlutterWalletController extends Controller
{
    public function balance(Request $request)
    {
        $user = $request->user();
        $balance = $user->wallet_balance;

        return response()->json([
            'success' => true,
            'data' => ['balance' => $balance]
        ]);
    }

    public function history(Request $request)
    {
        $transactions = WalletTransaction::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    public function charge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:card,cash',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        $transaction = WalletTransaction::create([
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => $request->amount,
            'description' => 'Wallet recharge',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Wallet charged successfully',
            'data' => [
                'transaction' => $transaction,
                'new_balance' => $user->wallet_balance
            ]
        ]);
    }
}
