<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCommission;
use App\Models\EmployeeCommissionWithdrawal;
use App\Models\JournalEntryHeader;
use App\Services\Accounting\LedgerPostingService;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeCommissionWithdrawalController extends Controller
{
    public function __construct(
        private CurrencyService $currencies,
        private LedgerPostingService $ledger,
    ) {
    }

    public function index(EmployeeCommission $employeeCommission)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $employeeCommission->withdrawalTransactions,
                'breakdown' => $employeeCommission->currencyBreakdown(),
                'total_base_amount' => (float) $employeeCommission->withdrawals,
            ],
        ]);
    }

    public function store(Request $request, EmployeeCommission $employeeCommission)
    {
        $validated = $this->validated($request);
        $data = array_merge($this->resolveAmounts($validated), ['created_by' => $request->user()?->id]);

        try {
            $withdrawal = DB::transaction(function () use ($employeeCommission, $data) {
                $withdrawal = $employeeCommission->withdrawalTransactions()->create($data);
                $employeeCommission->recalculateWithdrawals();
                // The financial settlement: the advance leaves the cash/bank
                // account and lands as a receivable against the employee, so
                // the withdrawal is reflected in the books the moment it is
                // recorded, not just in this sub-ledger.
                $this->ledger->postEmployeeCommissionWithdrawal($withdrawal);

                return $withdrawal;
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر ترحيل قيد السحب: ' . $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal recorded successfully',
            'data' => $withdrawal,
        ], 201);
    }

    /**
     * Financial fields are refused once the withdrawal has a live ledger
     * entry — editing them here would silently leave the posted entry
     * describing an amount, currency or account nobody withdrew. Delete the
     * withdrawal (which reverses the entry) and record a fresh one instead;
     * that keeps the books explained by an actual event rather than a
     * rewrite of one already posted.
     */
    public function update(Request $request, EmployeeCommission $employeeCommission, EmployeeCommissionWithdrawal $withdrawal)
    {
        abort_if($withdrawal->employee_commission_id !== $employeeCommission->id, 404);

        if ($this->hasLiveEntry($withdrawal)) {
            return response()->json([
                'success' => false,
                'message' => 'تم ترحيل هذا السحب إلى دفتر الأستاذ بالفعل، ولا يمكن تعديله. احذفه وسجّل عملية سحب جديدة بدلاً من ذلك.',
                'data' => null,
            ], 422);
        }

        $validated = $this->validated($request);

        $withdrawal->update($this->resolveAmounts($validated));

        $employeeCommission->recalculateWithdrawals();

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal updated successfully',
            'data' => $withdrawal->fresh(),
        ]);
    }

    /**
     * Soft delete: the row stays for audit/restore, but the money movement
     * is still cancelled immediately, the same as before — a hidden
     * withdrawal must not leave an uncancelled advance sitting in the books.
     * Restoring it later (see restore()) does not re-post that reversal.
     */
    public function destroy(Request $request, EmployeeCommission $employeeCommission, EmployeeCommissionWithdrawal $withdrawal)
    {
        abort_if($withdrawal->employee_commission_id !== $employeeCommission->id, 404);

        try {
            DB::transaction(function () use ($request, $employeeCommission, $withdrawal) {
                $this->ledger->reverseFor('employee_commission_withdrawal:' . $withdrawal->id);
                $withdrawal->update(['deleted_by' => $request->user()?->id]);
                $withdrawal->delete();
                $employeeCommission->recalculateWithdrawals();
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر عكس قيد السحب: ' . $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal deleted successfully',
        ]);
    }

    /** Admin-only review queue: soft-deleted withdrawals for this statement. */
    public function trashed(EmployeeCommission $employeeCommission)
    {
        return response()->json([
            'success' => true,
            'data' => $employeeCommission->withdrawalTransactions()
                ->onlyTrashed()
                ->with('deleter:id,name')
                ->orderByDesc('deleted_at')
                ->get(),
        ]);
    }

    /**
     * Un-hides a withdrawal for review. Does NOT re-post the ledger entry
     * that reverseFor() cancelled on delete — the reversal stays as the
     * historical record of what actually happened to the cash. Record a
     * fresh withdrawal if the advance genuinely needs to be reinstated.
     */
    public function restore(EmployeeCommission $employeeCommission, EmployeeCommissionWithdrawal $trashedWithdrawal)
    {
        abort_if($trashedWithdrawal->employee_commission_id !== $employeeCommission->id, 404);

        $trashedWithdrawal->restore();
        $trashedWithdrawal->update(['deleted_by' => null]);
        $employeeCommission->recalculateWithdrawals();

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal restored successfully',
            'data' => $trashedWithdrawal->fresh(),
        ]);
    }

    private function hasLiveEntry(EmployeeCommissionWithdrawal $withdrawal): bool
    {
        return JournalEntryHeader::where('reference_type', EmployeeCommissionWithdrawal::class)
            ->where('reference_id', $withdrawal->id)
            ->where('status', '!=', 'reversed')
            ->exists();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'withdrawn_at' => 'required|date',
            'currency_code' => 'required|string|max:10',
            'amount' => 'required|numeric|min:0.01',
            'exchange_rate' => 'nullable|numeric|min:0.00000001',
            'method' => 'required|in:cash,bank',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
    }

    /**
     * Turns what was typed — an amount in `currency_code` — into what the
     * ledger needs: the base-currency equivalent plus the rate that produced
     * it, resolved the same way a payment's amount is (see PaymentController)
     * so a withdrawal never invents a conversion the currency screen would
     * not also produce.
     *
     * An explicit `exchange_rate` overrides the recorded one — a cash advance
     * handed over at a street rate is real money at a rate nobody filed, and
     * refusing to record it would just push the difference into a balance
     * nobody can explain.
     */
    private function resolveAmounts(array $validated): array
    {
        $code = strtoupper(trim($validated['currency_code']));
        $amount = (float) $validated['amount'];
        $moment = Carbon::parse($validated['withdrawn_at']);
        $baseCode = $this->currencies->baseCode();

        if ($code === $baseCode) {
            return array_merge($validated, [
                'currency_code' => $code,
                'exchange_rate' => 1,
                // Matches the converted branch below, which already defers to the
                // currency's own configured precision instead of a literal here.
                'base_amount' => $this->currencies->round($amount, $this->currencies->base()),
            ]);
        }

        if (!empty($validated['exchange_rate'])) {
            $rate = (float) $validated['exchange_rate'];
            $baseAmount = $this->currencies->round($amount / $rate, $this->currencies->base());
        } else {
            $rate = (float) ($this->currencies->rateFor($code, $moment) ?? 0);
            $baseAmount = $this->currencies->convertToBase($amount, $code, $moment);

            abort_if($baseAmount === null, 422, "لا يوجد سعر صرف مسجّل للعملة {$code} عند هذا التاريخ. سجّل السعر من إدارة العملات أو أدخله يدوياً.");
        }

        return array_merge($validated, [
            'currency_code' => $code,
            'exchange_rate' => $rate,
            'base_amount' => $baseAmount,
        ]);
    }
}
