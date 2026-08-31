<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeCommission;
use App\Models\EmployeeCommissionWithdrawal;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeCommissionController extends Controller
{
    public function __construct(private LedgerPostingService $ledger)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $query = EmployeeCommission::with('employee:id,first_name,last_name')
            ->orderBy('employee_id')
            ->orderBy('month');

        if (!empty($validated['employee_id'])) {
            $query->where('employee_id', $validated['employee_id']);
        }

        if (!empty($validated['year'])) {
            $query->whereYear('month', $validated['year']);
        }

        $records = $query->get();

        // Cumulative balance only makes sense against every prior month of
        // the same employee, so it is carried per employee_id, not globally.
        $running = [];
        $data = $records->map(function (EmployeeCommission $record) use (&$running) {
            $statement = $record->toStatement();
            $running[$record->employee_id] = ($running[$record->employee_id] ?? 0) + $statement['balance'];
            $statement['cumulative_balance'] = round($running[$record->employee_id], 2);
            return $statement;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function calculateSales(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|date',
        ]);

        $result = EmployeeCommission::computeSalesForMonth(
            (int) $validated['employee_id'],
            Carbon::parse($validated['month'])
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|date',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'extra_expenses' => 'nullable|numeric|min:0',
            'monthly_target' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $month = Carbon::parse($validated['month'])->startOfMonth();

        $sales = EmployeeCommission::computeSalesForMonth((int) $validated['employee_id'], $month);

        // `withdrawals` is never set here: it is owned by the transaction
        // ledger (see EmployeeCommissionWithdrawalController) and left at
        // whatever recalculateWithdrawals() last computed — 0 for a brand
        // new record, untouched for one updateOrCreate finds again.
        $record = EmployeeCommission::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'month' => $month->toDateString()],
            [
                'commission_rate' => $validated['commission_rate'],
                'total_sales' => $sales['total_sales'],
                'extra_expenses' => $validated['extra_expenses'] ?? 0,
                'monthly_target' => $validated['monthly_target'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Commission record saved successfully',
            'data' => $record->toStatement(),
        ], 201);
    }

    public function update(Request $request, EmployeeCommission $employeeCommission)
    {
        $validated = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:100',
            'extra_expenses' => 'nullable|numeric|min:0',
            'monthly_target' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'recalculate_sales' => 'nullable|boolean',
        ]);

        if (!empty($validated['recalculate_sales'])) {
            $sales = EmployeeCommission::computeSalesForMonth(
                $employeeCommission->employee_id,
                $employeeCommission->month
            );
            $employeeCommission->total_sales = $sales['total_sales'];
        }

        // `withdrawals` is deliberately left out — see store().
        $employeeCommission->update([
            'commission_rate' => $validated['commission_rate'],
            'extra_expenses' => $validated['extra_expenses'] ?? 0,
            'monthly_target' => $validated['monthly_target'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission record updated successfully',
            'data' => $employeeCommission->fresh()->toStatement(),
        ]);
    }

    /**
     * Soft delete, cascaded to the withdrawal ledger: every active withdrawal
     * under this statement is reversed in the books (same as deleting it on
     * its own would do) and soft-deleted alongside the statement, so nothing
     * hidden here still carries a live, unreversed ledger entry. `withdrawals`
     * is left at its pre-delete value rather than recalculated to 0 — it is
     * now a snapshot of what the statement looked like at the moment it was
     * removed, for the trash/audit view.
     */
    public function destroy(Request $request, EmployeeCommission $employeeCommission)
    {
        $userId = $request->user()?->id;

        try {
            DB::transaction(function () use ($employeeCommission, $userId) {
                $employeeCommission->withdrawalTransactions->each(function (EmployeeCommissionWithdrawal $withdrawal) use ($userId) {
                    $this->ledger->reverseFor('employee_commission_withdrawal:' . $withdrawal->id);
                    $withdrawal->update(['deleted_by' => $userId]);
                    $withdrawal->delete();
                });

                $employeeCommission->update(['deleted_by' => $userId]);
                $employeeCommission->delete();
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر حذف كشف الحساب: ' . $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Commission record deleted successfully',
        ]);
    }

    /** Admin-only review queue: soft-deleted statements, for audit or restore. */
    public function trashed(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $query = EmployeeCommission::onlyTrashed()
            ->with(['employee:id,first_name,last_name', 'deleter:id,name'])
            ->orderByDesc('deleted_at');

        if (!empty($validated['employee_id'])) {
            $query->where('employee_id', $validated['employee_id']);
        }

        if (!empty($validated['year'])) {
            $query->whereYear('month', $validated['year']);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn (EmployeeCommission $record) => $record->toStatement()),
        ]);
    }

    /**
     * Restores a soft-deleted statement so it reappears in the normal
     * listing. Deliberately does not cascade to its withdrawals: some of
     * those may have been trashed independently, before this statement ever
     * was, and resurrecting all of them would silently undo unrelated
     * decisions. Restore a withdrawal on its own from the trash panel in the
     * withdrawals dialog when it genuinely belongs back.
     *
     * The financial settlement: `withdrawals` was frozen at its pre-delete
     * value while trashed (a snapshot for the trash/audit view), but every
     * withdrawal cascade-deleted alongside it is still sitting in the trash
     * with its ledger entry reversed — none of that total is backed by a
     * live transaction any more. recalculateWithdrawals() re-sums whatever
     * is actually active right now (0 unless some were independently
     * restored first), so the reappearing statement's balance reflects
     * reality instead of a stale pre-delete number.
     */
    public function restore(EmployeeCommission $trashedEmployeeCommission)
    {
        $trashedEmployeeCommission->restore();
        $trashedEmployeeCommission->update(['deleted_by' => null]);
        $trashedEmployeeCommission->recalculateWithdrawals();

        return response()->json([
            'success' => true,
            'message' => 'Commission record restored successfully',
            'data' => $trashedEmployeeCommission->fresh()->toStatement(),
        ]);
    }
}
