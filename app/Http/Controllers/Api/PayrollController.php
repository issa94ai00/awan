<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Attendance;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Payroll, and the two moments it touches the ledger.
 *
 * Nothing here reached the books before: salaries were computed, stored and
 * marked paid without a single journal entry, so wages — usually the largest
 * recurring cost a business has — showed up in no income statement, and the
 * cash they consumed was unexplained.
 *
 * Two events, deliberately kept apart:
 *
 *  - **processed** recognises the cost against the period that was worked.
 *  - **paid** settles the liability that accrual raised.
 *
 * Collapsing them would date wages to the day the transfer cleared, so a month
 * closed before payday would report no salaries at all.
 */
class PayrollController extends Controller
{
    public function __construct(private LedgerPostingService $ledger)
    {
    }

    /**
     * The next payroll number.
     *
     * Derived from the last id rather than a count: counting hands out a number
     * that is already taken as soon as any payroll is deleted, and the column
     * is unique — so the next payroll run failed outright.
     */
    private function nextPayrollNumber(): string
    {
        return 'PAY-' . str_pad((string) (((int) Payroll::max('id')) + 1), 6, '0', STR_PAD_LEFT);
    }

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
            // Says what the deduction is: an advance being repaid reduces the
            // advance rather than raising a liability the business now holds.
            'deduction_type' => 'nullable|in:general,advance',
            'payment_method' => 'nullable|in:cash,bank_transfer,check',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['payroll_number'] = $this->nextPayrollNumber();
        $validated['status'] = Payroll::STATUS_PENDING;
        $validated['created_by'] = auth()->id();

        // Created pending and unposted on purpose: a draft payroll is a
        // calculation, not yet a cost the business has incurred.
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

    /**
     * Edits a payroll, and moves it along its lifecycle.
     *
     * Once the cost is on the books the figures behind it stop being editable:
     * changing the salary of a payroll whose expense has already been reported
     * would leave the record describing one amount and the ledger another, with
     * nothing to say which is right. The status, the payment details and the
     * notes stay editable, because those are what the lifecycle is made of.
     */
    public function update(Request $request, Payroll $payroll)
    {
        $accrued = $payroll->isAccrued();

        $rules = [
            'payment_method' => 'nullable|in:cash,bank_transfer,check',
            'status' => 'required|in:pending,processed,paid',
            'notes' => 'nullable|string|max:1000',
            // Unqualified once the payroll is posted: the period is no longer
            // part of this payload, so a rule comparing the payment date
            // against it would be comparing against a field never sent.
            'payment_date' => 'nullable|date',
        ];

        if (! $accrued) {
            $rules = array_merge($rules, [
                'employee_id' => 'required|exists:employees,id',
                'pay_period_start' => 'required|date',
                'pay_period_end' => 'required|date|after:pay_period_start',
                'payment_date' => 'nullable|date|after_or_equal:pay_period_end',
                'basic_salary' => 'required|numeric|min:0',
                'overtime_hours' => 'nullable|numeric|min:0',
                'overtime_rate' => 'nullable|numeric|min:0',
                'bonuses' => 'nullable|numeric|min:0',
                'deductions' => 'nullable|numeric|min:0',
                'deduction_type' => 'nullable|in:general,advance',
            ]);
        }

        $validated = $request->validate($rules);

        // Reverting a posted payroll to pending would strand its entries: the
        // expense would stay on the books describing a payroll that claims not
        // to have happened.
        if ($accrued && $validated['status'] === Payroll::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إعادة مسيرة رُحِّلت إلى الدفاتر لحالة "معلّق". سجّل قيداً عكسياً إن كان الاستحقاق خاطئاً.',
                'data' => null,
            ], 422);
        }

        try {
            DB::transaction(function () use ($payroll, $validated, $accrued) {
                $payroll->update($validated);

                if (! $accrued) {
                    $payroll->calculateNetSalary();
                    $payroll->save();
                }

                // Processed and paid both mean the wage was earned, so both
                // accrue. A payroll paid without ever passing through
                // "processed" would otherwise have its cost recognised nowhere.
                if (in_array($payroll->status, [Payroll::STATUS_PROCESSED, Payroll::STATUS_PAID], true)) {
                    $this->ledger->postPayrollAccrual($payroll->load('employee'));
                }

                if ($payroll->status === Payroll::STATUS_PAID) {
                    $this->ledger->postPayrollPayment($payroll);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر ترحيل قيد الرواتب: ' . $e->getMessage(),
                'data' => null,
            ], 422);
        }

        $payroll->load(['employee', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث مسيرة الرواتب بنجاح',
            'data' => $payroll
        ]);
    }

    /**
     * Pays out an employee's end-of-service benefit.
     *
     * Settles the liability the monthly accruals built up. The cost was
     * recognised in the years that earned it, so nothing is charged again here
     * — which is the whole point of accruing it monthly rather than dropping
     * years of expense into the month somebody happens to leave.
     */
    public function settleEndOfService(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'settlement' => 'nullable|in:cash,bank',
            'paid_on' => 'nullable|date',
        ]);

        $accrued = round((float) $employee->end_of_service_accrued, 2);
        $amount = round((float) ($validated['amount'] ?? $accrued), 2);

        if ($accrued <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد مستحق نهاية خدمة مجمَّع لهذا الموظف.',
                'data' => null,
            ], 422);
        }

        // Paying more than was accrued would debit a liability that was never
        // raised, leaving the account negative and the extra unexplained.
        if ($amount > $accrued + 0.009) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'المبلغ (%s) يتجاوز المستحق المجمَّع (%s). استحقّ الأشهر الناقصة أولاً.',
                    number_format($amount, 2),
                    number_format($accrued, 2)
                ),
                'data' => null,
            ], 422);
        }

        try {
            DB::transaction(function () use ($employee, $amount, $validated) {
                $this->ledger->postEndOfServiceSettlement(
                    $employee,
                    $amount,
                    $validated['settlement'] ?? 'cash',
                    $validated['paid_on'] ?? null
                );

                $employee->forceFill([
                    'end_of_service_accrued' => round((float) $employee->end_of_service_accrued - $amount, 2),
                ])->save();
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر ترحيل قيد الصرف: '.$e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم صرف مكافأة نهاية الخدمة وترحيل قيدها',
            'data' => $employee->refresh(),
        ]);
    }

    /**
     * Deleting a payroll that has reached the ledger is refused.
     *
     * Its entries would be left pointing at a document that no longer exists,
     * and the salaries expense for a closed period would keep a figure nobody
     * can trace back to a person or a month.
     */
    public function destroy(Payroll $payroll)
    {
        if ($payroll->isAccrued()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مسيرة رواتب رُحّل استحقاقها. اعكس قيدها من شاشة اليومية إن كانت خاطئة.',
                'data' => null,
            ], 422);
        }

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
                'payroll_number' => $this->nextPayrollNumber(),
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
