<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $query = Payroll::with(['employee', 'creator']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        $payrolls = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Payrolls retrieved successfully',
            'data' => [
                'payrolls' => $payrolls->items(),
                'pagination' => [
                    'current_page' => $payrolls->currentPage(),
                    'last_page' => $payrolls->lastPage(),
                    'per_page' => $payrolls->perPage(),
                    'total' => $payrolls->total(),
                    'has_more_pages' => $payrolls->hasMorePages(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'payment_date' => 'nullable|date|after_or_equal:pay_period_end',
            'basic_salary' => 'required|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:0',
            'bonuses' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['payroll_number'] = 'PAY-' . str_pad(Payroll::count() + 1, 6, '0', STR_PAD_LEFT);
        $validated['status'] = Payroll::STATUS_PENDING;
        $validated['created_by'] = auth()->id();

        $payroll = Payroll::create($validated);
        $payroll->calculateNetSalary();
        $payroll->save();

        $payroll->load(['employee', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء مسيرة الرواتب بنجاح',
            'data' => $payroll
        ], 201);
    }

    public function show(Payroll $payroll)
    {
        $payroll->load(['employee', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Payroll retrieved successfully',
            'data' => $payroll
        ]);
    }

    public function update(Request $request, Payroll $payroll)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'payment_date' => 'nullable|date|after_or_equal:pay_period_end',
            'basic_salary' => 'required|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:0',
            'bonuses' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending,processed,paid',
            'notes' => 'nullable|string|max:1000',
        ]);

        $payroll->update($validated);
        $payroll->calculateNetSalary();
        $payroll->save();

        $payroll->load(['employee', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث مسيرة الرواتب بنجاح',
            'data' => $payroll
        ]);
    }

    public function destroy(Payroll $payroll)
    {
        $payroll->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف مسيرة الرواتب بنجاح',
            'data' => null
        ]);
    }

    public function autoGenerate(Request $request)
    {
        $validated = $request->validate([
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'payment_date' => 'nullable|date|after_or_equal:pay_period_end',
        ]);

        $employees = Employee::all();
        $createdCount = 0;

        foreach ($employees as $employee) {
            $attendances = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$validated['pay_period_start'], $validated['pay_period_end']])
                ->get();

            $overtimeHours = $attendances->sum('overtime_hours');
            $overtimeRate = $employee->hourly_rate ?? 0;

            $payroll = Payroll::create([
                'payroll_number' => 'PAY-' . str_pad(Payroll::count() + 1, 6, '0', STR_PAD_LEFT),
                'employee_id' => $employee->id,
                'pay_period_start' => $validated['pay_period_start'],
                'pay_period_end' => $validated['pay_period_end'],
                'payment_date' => $validated['payment_date'],
                'basic_salary' => $employee->salary ?? 0,
                'overtime_hours' => $overtimeHours,
                'overtime_rate' => $overtimeRate,
                'bonuses' => 0,
                'deductions' => 0,
                'status' => Payroll::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]);

            $payroll->calculateNetSalary();
            $payroll->save();
            $createdCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "تم إنشاء {$createdCount} مسيرة رواتب بنجاح",
            'data' => ['created_count' => $createdCount]
        ], 201);
    }
}
