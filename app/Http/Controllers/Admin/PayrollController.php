<?php

namespace App\Http\Controllers\Admin;

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

        if ($request->has('search') && $request->search) {
            $query->where('payroll_number', 'like', '%' . $request->search . '%')
                ->orWhereHas('employee', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
        }

        $payrolls = $query->latest()->paginate(20);
        $employees = Employee::all();

        return view('admin.payrolls.index', compact('payrolls', 'employees'));
    }

    public function create()
    {
        $employees = Employee::all();
        return view('admin.payrolls.create', compact('employees'));
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

        return redirect()->route('admin.payrolls.index')
            ->with('success', 'تم إنشاء مسيرة الرواتب بنجاح');
    }

    public function show(Payroll $payroll)
    {
        $payroll->load(['employee', 'creator']);
        return view('admin.payrolls.show', compact('payroll'));
    }

    public function edit(Payroll $payroll)
    {
        $payroll->load('employee');
        $employees = Employee::all();
        return view('admin.payrolls.edit', compact('payroll', 'employees'));
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

        return redirect()->route('admin.payrolls.index')
            ->with('success', 'تم تحديث مسيرة الرواتب بنجاح');
    }

    public function destroy(Payroll $payroll)
    {
        $payroll->delete();

        return redirect()->route('admin.payrolls.index')
            ->with('success', 'تم حذف مسيرة الرواتب بنجاح');
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
            // Calculate overtime hours from attendance
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

        return redirect()->route('admin.payrolls.index')
            ->with('success', "تم إنشاء {$createdCount} مسيرة رواتب بنجاح");
    }
}
