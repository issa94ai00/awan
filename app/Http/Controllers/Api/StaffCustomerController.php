<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffCustomerController extends Controller
{
    /**
     * Get customers associated with the authenticated staff member
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get the employee record for the authenticated user
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على سجل الموظف',
            ], 404);
        }

        $customers = Customer::where('employee_id', $employee->id)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email', 'company', 'address']);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Add a new customer linked to the authenticated staff member
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get the employee record for the authenticated user
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على سجل الموظف',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
        ], [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مسجل مسبقاً',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً',
            'email.email' => 'البريد الإلكتروني غير صحيح',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = Customer::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'company' => $request->company,
            'address' => $request->address,
            'city' => $request->city,
            'tax_number' => $request->tax_number,
            'employee_id' => $employee->id,
            'source' => 'staff_created',
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العميل بنجاح',
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'company' => $customer->company,
                'address' => $customer->address,
                'employee_id' => $customer->employee_id,
            ],
        ], 201);
    }

    /**
     * Get a specific customer by ID (if associated with the staff member)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على سجل الموظف',
            ], 404);
        }

        $customer = Customer::where('id', $id)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'العميل غير موجود',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    /**
     * Update a customer (if associated with the staff member)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على سجل الموظف',
            ], 404);
        }

        $customer = Customer::where('id', $id)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'العميل غير موجود',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:customers,phone,' . $id,
            'email' => 'nullable|email|max:255|unique:customers,email,' . $id,
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
        ], [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مسجل مسبقاً',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً',
            'email.email' => 'البريد الإلكتروني غير صحيح',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer->update($request->only([
            'name', 'phone', 'email', 'company', 'address', 'city', 'tax_number'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث العميل بنجاح',
            'data' => $customer,
        ]);
    }
}
