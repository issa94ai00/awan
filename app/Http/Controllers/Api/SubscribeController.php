<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Support\TrustedEmployee;

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
            'employee_id' => 'nullable|exists:employees,id',
        ], [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'employee_id.exists' => 'موظف المبيعات غير موجود',
        ]);

        // Only a rep signed in to the app may credit the subscriber to a rep.
        $employeeId = TrustedEmployee::resolve($validated['employee_id'] ?? null);

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
                'employee_id' => $employeeId ?? $subscriber->employee_id,
            ]);
        } else {
            $subscriber = Subscriber::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'is_active' => true,
                'employee_id' => $employeeId,
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

            if ($customer) {
                // An existing customer is never re-keyed from here. Setting a
                // new password for whoever typed a known phone number, and
                // handing back a login token, let anyone take over that
                // customer's account. They sign in, or reset their password.
                return response()->json([
                    'success' => false,
                    'message' => 'هذا الرقم مرتبط بحساب موجود. سجّل الدخول أو استعد كلمة المرور.',
                    'data' => ['account_exists' => true],
                ], 409);
            }

            $token = Str::random(80);

            $customer = Customer::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'password' => Hash::make($validated['password']),
                'auth_token' => $token,
                'source' => 'subscriber',
                'status' => 'active',
                'employee_id' => $employeeId,
            ]);
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
                    'employee_id' => $customer->employee_id,
                ],
                'token' => $token,
            ];
        }

        return response()->json($response, $subscriber->wasRecentlyCreated ? 201 : 200);
    }
}
