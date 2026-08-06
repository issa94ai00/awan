<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'password' => 'required|string|min:6',
            'employee_id' => 'nullable|exists:employees,id',
        ], [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مسجل مسبقاً',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'employee_id.exists' => 'موظف المبيعات غير موجود',
        ]);

        $token = Str::random(80);

        $customer = Customer::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'auth_token' => $token,
            'source' => 'customer_register',
            'status' => 'active',
            'employee_id' => $validated['employee_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم التسجيل بنجاح',
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'employee_id' => $customer->employee_id,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required_without:email|nullable|string|max:20',
            'email' => 'required_without:phone|nullable|email',
            'password' => 'required|string',
        ], [
            'phone.required_without' => 'يجب إدخال رقم الهاتف أو البريد الإلكتروني',
            'email.required_without' => 'يجب إدخال رقم الهاتف أو البريد الإلكتروني',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'password.required' => 'كلمة المرور مطلوبة',
        ]);

        $customer = $request->filled('phone')
            ? Customer::where('phone', $validated['phone'])->first()
            : Customer::where('email', $validated['email'])->first();

        if (!$customer || !Hash::check($validated['password'], $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة',
            ], 401);
        }

        if (!$customer->auth_token) {
            $customer->update(['auth_token' => Str::random(80)]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'employee_id' => $customer->employee_id,
                ],
                'token' => $customer->auth_token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $customer = Customer::where('auth_token', $token)->first();

        if ($customer) {
            $customer->update(['auth_token' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $customer = Customer::where('auth_token', $token)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'employee_id' => $customer->employee_id,
            ],
        ]);
    }
}
