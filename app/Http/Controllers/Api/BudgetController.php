<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\LedgerAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The figure set in advance, and how the year measured against it.
 *
 * Every other report here answers what happened. None of them says whether
 * what happened was what was meant to: an expense account showing 40,000 for
 * the quarter is a fact with no verdict, either well under control or a
 * serious overrun, and only a budget tells the two apart.
 *
 * The actual side is read from the ledger rather than entered beside it, so a
 * variance report and the income statement can never disagree about what was
 * spent.
 */
class BudgetController extends Controller
{
    private const EPSILON = 0.005;

    /** Entry statuses that never reached the books. */
    private const UNPOSTED_STATUSES = ['draft', 'pending', 'void', 'cancelled'];

    public function index(Request $request): JsonResponse
    {
        $budgets = Budget::withCount('lines')
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->fiscal_year))
            ->orderByDesc('fiscal_year')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Budgets retrieved successfully',
            'data' => [
                'budgets' => $budgets,
                // Only accounts a budget is meaningful for: a balance-sheet
                // account is a position, not something a year plans to spend.
                'accounts' => LedgerAccount::whereIn('type', ['revenue', 'expense'])
                    ->orderBy('code')
                    ->get(['id', 'code', 'name', 'type']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'fiscal_year' => 'required|integer|min:2000|max:2100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (Budget::where('fiscal_year', $validated['fiscal_year'])->where('name', $validated['name'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'توجد موازنة بهذا الاسم لنفس السنة. أنشئ نسخة باسم آخر بدل الكتابة فوق أرقام صدرت تقاريرها.',
                'data' => null,
            ], 422);
        }

        $budget = Budget::create($validated + [
            'status' => Budget::STATUS_DRAFT,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الموازنة',
            'data' => $budget,
        ], 201);
    }

    public function show(Budget $budget): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Budget retrieved successfully',
            'data' => [
                'budget' => $budget,
                'lines' => $budget->lines()->with('account:id,code,name,type')->get(),
            ],
        ]);
    }

    /**
     * Sets the figures for one or more account-months.
     *
     * Replaces rather than adds: setting a month again is a correction of that
     * month, not a second budget for it.
     */
    public function setLines(Request $request, Budget $budget): JsonResponse
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.account_id' => 'required|exists:ledger_accounts,id',
            'lines.*.month' => 'required|integer|min:1|max:12',
            'lines.*.amount' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($budget, $validated) {
            foreach ($validated['lines'] as $line) {
                BudgetLine::updateOrCreate(
                    [
                        'budget_id' => $budget->id,
                        'account_id' => $line['account_id'],
                        'month' => $line['month'],
                    ],
                    ['amount' => round((float) $line['amount'], 2)]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ أرقام الموازنة',
            'data' => $budget->lines()->with('account:id,code,name,type')->get(),
        ]);
    }

    /**
     * Budget against actual for a span of months.
     *
     * Variance is expressed as "better or worse than planned" rather than a
     * raw subtraction, because the two sides of the income statement read in
     * opposite directions: spending less than budgeted is good news and earning
     * less than budgeted is not, and a single signed number would show both as
     * negative.
     */
    public function variance(Request $request, Budget $budget): JsonResponse
    {
        $validated = $request->validate([
            'from_month' => 'nullable|integer|min:1|max:12',
            'to_month' => 'nullable|integer|min:1|max:12',
        ]);

        $fromMonth = (int) ($validated['from_month'] ?? 1);
        $toMonth = (int) ($validated['to_month'] ?? 12);

        if ($fromMonth > $toMonth) {
            [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
        }

        $from = sprintf('%04d-%02d-01', $budget->fiscal_year, $fromMonth);
        $to = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $budget->fiscal_year, $toMonth)));

        $planned = $budget->lines()
            ->whereBetween('month', [$fromMonth, $toMonth])
            ->selectRaw('account_id, SUM(amount) as amount')
            ->groupBy('account_id')
            ->pluck('amount', 'account_id');

        $actuals = DB::table('journal_entry_lines as l')
            ->join('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
            ->whereNull('h.deleted_at')
            ->whereNotIn('h.status', self::UNPOSTED_STATUSES)
            ->whereBetween(DB::raw('DATE(h.entry_date)'), [$from, $to])
            ->whereIn('l.account_id', $planned->keys()->all() ?: [0])
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')
            ->get()
            ->keyBy('account_id');

        $accounts = LedgerAccount::whereIn('id', $planned->keys()->all() ?: [0])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $rows = $accounts->map(function (LedgerAccount $account) use ($planned, $actuals) {
            $row = $actuals->get($account->id);

            $actual = round(LedgerAccount::signedDelta(
                $account->type,
                (float) ($row->d ?? 0),
                (float) ($row->c ?? 0)
            ), 2);

            $budgeted = round((float) ($planned[$account->id] ?? 0), 2);

            // Under budget is good for a cost and bad for a revenue, so the
            // verdict is computed per side rather than left as a raw difference
            // the reader has to reinterpret account by account.
            $difference = round($actual - $budgeted, 2);
            $favourable = $account->type === 'revenue' ? $difference >= 0 : $difference <= 0;

            return [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'budget' => $budgeted,
                'actual' => $actual,
                'difference' => $difference,
                'percentage' => abs($budgeted) > self::EPSILON
                    ? round(($actual / $budgeted) * 100, 1)
                    : null,
                'is_favourable' => abs($difference) < self::EPSILON ? true : $favourable,
            ];
        });

        $summarise = fn (string $type) => [
            'budget' => round($rows->where('type', $type)->sum('budget'), 2),
            'actual' => round($rows->where('type', $type)->sum('actual'), 2),
        ];

        $revenue = $summarise('revenue');
        $expense = $summarise('expense');

        return response()->json([
            'success' => true,
            'message' => 'Budget variance retrieved successfully',
            'data' => [
                'budget' => $budget,
                'period' => ['from' => $from, 'to' => $to, 'months' => [$fromMonth, $toMonth]],
                'rows' => $rows->values(),
                'totals' => [
                    'revenue' => $revenue,
                    'expenses' => $expense,
                    // The bottom line both ways, which is the figure anybody
                    // reading a budget report is actually looking for.
                    'planned_result' => round($revenue['budget'] - $expense['budget'], 2),
                    'actual_result' => round($revenue['actual'] - $expense['actual'], 2),
                ],
            ],
        ]);
    }

    /** A budget with figures behind it is superseded, not deleted. */
    public function destroy(Budget $budget): JsonResponse
    {
        if ($budget->status === Budget::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'لا تُحذف موازنة معتمدة. أنشئ نسخة جديدة إن تغيّرت الخطة.',
                'data' => null,
            ], 422);
        }

        $budget->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الموازنة',
            'data' => null,
        ]);
    }

    /** Approving freezes a budget as the figure the year is judged against. */
    public function approve(Budget $budget): JsonResponse
    {
        $budget->update(['status' => Budget::STATUS_APPROVED]);

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد الموازنة',
            'data' => $budget->refresh(),
        ]);
    }
}
