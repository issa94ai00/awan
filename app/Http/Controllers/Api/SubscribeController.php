<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SubscribeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'password' => 'nullable|string|min:6',
        ], [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
        ]);

        // Save or update subscriber
        $subscriber = Subscriber::where('phone', $validated['phone'])->first();
        if (!$subscriber && !empty($validated['email'])) {
            $subscriber = Subscriber::where('email', $validated['email'])->first();
        }

        if ($subscriber) {
            $subscriber->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? $subscriber->email,
                'phone' => $validated['phone'],
                'is_active' => true,
            ]);
        } else {
            $subscriber = Subscriber::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'is_active' => true,
            ]);
        }

        // If password is provided, create or update customer account
        $token = null;
        $customer = null;

        if (!empty($validated['password'])) {
            $customer = Customer::where('phone', $validated['phone'])->first();
            if (!$customer && !empty($validated['email'])) {
                $customer = Customer::where('email', $validated['email'])->first();
            }

            $token = Str::random(80);

            if ($customer) {
                $customer->update([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? $customer->email,
                    'address' => $validated['address'] ?? $customer->address,
                    'password' => Hash::make($validated['password']),
                    'auth_token' => $token,
                ]);
            } else {
                $customer = Customer::create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'password' => Hash::make($validated['password']),
                    'auth_token' => $token,
                    'source' => 'subscriber',
                    'status' => 'active',
                ]);
            }
        }

        $response = [
            'success' => true,
            'message' => 'تم الاشتراك بنجاح، شكراً لك!',
        ];

        if ($customer && $token) {
            $response['data'] = [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'address' => $customer->address,
                ],
                'token' => $token,
            ];
        }

        return response()->json($response, $subscriber->wasRecentlyCreated ? 201 : 200);
    }
}
