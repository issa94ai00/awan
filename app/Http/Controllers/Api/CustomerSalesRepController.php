<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Which employees serve a customer, and which of them new sales default to.
 *
 * An admin's call (the route is behind role:admin): it decides whose customer
 * list the customer appears in and who a sale is credited to.
 */
class CustomerSalesRepController extends Controller
{
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'primary_employee_id' => 'nullable|integer|exists:employees,id',
            'employee_ids' => 'present|array',
            'employee_ids.*' => 'integer|exists:employees,id',
        ], [
            'primary_employee_id.exists' => 'الموظف المحدد غير موجود',
            'employee_ids.*.exists' => 'أحد الموظفين المحددين غير موجود',
        ]);

        DB::transaction(fn () => $customer->setSalesReps(
            isset($validated['primary_employee_id']) ? (int) $validated['primary_employee_id'] : null,
            $validated['employee_ids'] ?? []
        ));

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث مندوبي العميل',
            'data' => self::repsOf($customer->fresh()),
        ]);
    }

    /** @return array{employee_id: int|null, sales_reps: list<array{id:int,name:string,is_primary:bool}>} */
    public static function repsOf(Customer $customer): array
    {
        return [
            'employee_id' => $customer->employee_id ? (int) $customer->employee_id : null,
            'sales_reps' => $customer->customerEmployees()->get()->map(fn ($e) => [
                'id' => (int) $e->id,
                'name' => $e->name,
                'is_primary' => (bool) $e->pivot->is_primary,
            ])->values()->all(),
        ];
    }
}
