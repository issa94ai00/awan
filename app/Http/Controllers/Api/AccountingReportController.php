<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingReportController extends Controller
{
    /** Rounding tolerance when deciding whether a report balances. */
    private const EPSILON = 0.005;

    /**
     * Trial balance for a period.
     *
     * Movements are aggregated in SQL rather than by loading every journal line
     * into memory, and the period is honoured — the previous version summed the
     * whole ledger regardless of the dates the user picked, and reused the
     * running `balance` column so the "balance" shown never matched the debit
     * and credit columns beside it.
     */
    public function trialBalance(Request $request): JsonResponse
    {
        [$fromDate, $toDate] = $this->period($request);

        $rows = DB::table('ledger_accounts as a')
            ->leftJoin('journal_entry_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entry_headers as h', function ($join) use ($fromDate, $toDate) {
                $join->on('h.id', '=', 'l.journal_entry_header_id')
                    ->whereNull('h.deleted_at')
                    ->whereBetween('h.entry_date', [$fromDate, $toDate]);
            })
            ->selectRaw('a.id, a.code, a.name, a.type, a.posting_role,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.debit END), 0) as debits,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.credit END), 0) as credits')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.posting_role')
            ->orderBy('a.code')
            ->get();

        $accounts = $rows->map(function ($row) {
            $debits = (float) $row->debits;
            $credits = (float) $row->credits;

            return [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'debits' => round($debits, 2),
                'credits' => round($credits, 2),
                // Closing movement for the period, on the account's normal side.
                'balance' => round(LedgerAccount::signedDelta($row->type, $debits, $credits), 2),
            ];
        });

        $totalDebits = round($accounts->sum('debits'), 2);
        $totalCredits = round($accounts->sum('credits'), 2);

        return response()->json([
            'success' => true,
            'message' => 'Trial balance retrieved successfully',
            'data' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                // Accounts with no movement in the period only add noise.
                'accounts' => $accounts->filter(fn ($a) => $a['debits'] != 0.0 || $a['credits'] != 0.0)->values(),
                'all_accounts' => $accounts,
                'totals' => ['debits' => $totalDebits, 'credits' => $totalCredits],
                'difference' => round($totalDebits - $totalCredits, 2),
                'is_balanced' => abs($totalDebits - $totalCredits) < self::EPSILON,
                // Surfaced so a corrupt entry is visible here instead of quietly
                // skewing every downstream statement.
                'unbalanced_entries' => $this->unbalancedEntries($fromDate, $toDate),
            ],
        ]);
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        [$fromDate, $toDate] = $this->period($request);

        $rows = $this->movementsByType(['revenue', 'expense'], $fromDate, $toDate);

        $revenue = $rows->where('type', 'revenue')->map(fn ($r) => [
            'id' => $r->id,
            'code' => $r->code,
            'name' => $r->name,
            // Contra-revenue accounts (returns, discounts) are debit-heavy and
            // therefore come out negative, which is what nets them off revenue.
            'amount' => round((float) $r->credits - (float) $r->debits, 2),
        ])->values();

        $expenses = $rows->where('type', 'expense')->map(fn ($r) => [
            'id' => $r->id,
            'code' => $r->code,
            'name' => $r->name,
            'amount' => round((float) $r->debits - (float) $r->credits, 2),
        ])->values();

        $totalRevenue = round($revenue->sum('amount'), 2);
        $totalExpense = round($expenses->sum('amount'), 2);

        return response()->json([
            'success' => true,
            'message' => 'Income statement retrieved successfully',
            'data' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'revenue' => ['total' => $totalRevenue, 'accounts' => $revenue],
                'expenses' => ['total' => $totalExpense, 'accounts' => $expenses],
                'net_income' => round($totalRevenue - $totalExpense, 2),
            ],
        ]);
    }

    /**
     * Balance sheet as at a date.
     *
     * The previous version listed asset, liability and equity balances and then
     * checked A = L + E — which could never be true, because the period's
     * profit lives in the revenue and expense accounts until it is closed out.
     * The result is now carried into equity as "current period result", exactly
     * as a closing entry would, so the statement balances whenever the
     * underlying journal does.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $asOf = $request->input('as_of', now()->toDateString());
        $from = '1900-01-01';

        $rows = $this->movementsByType(['asset', 'liability', 'equity', 'revenue', 'expense'], $from, $asOf);

        $balanceOf = fn ($row) => round(
            LedgerAccount::signedDelta($row->type, (float) $row->debits, (float) $row->credits),
            2
        );

        $group = fn (string $type) => $rows->where('type', $type)
            ->map(fn ($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'balance' => $balanceOf($r),
            ])
            ->filter(fn ($a) => abs($a['balance']) > self::EPSILON)
            ->values();

        $assets = $group('asset');
        $liabilities = $group('liability');
        $equity = $group('equity');

        $totalRevenue = round($rows->where('type', 'revenue')->sum(fn ($r) => (float) $r->credits - (float) $r->debits), 2);
        $totalExpense = round($rows->where('type', 'expense')->sum(fn ($r) => (float) $r->debits - (float) $r->credits), 2);
        $currentResult = round($totalRevenue - $totalExpense, 2);

        $totalAssets = round($assets->sum('balance'), 2);
        $totalLiabilities = round($liabilities->sum('balance'), 2);
        $totalEquityPosted = round($equity->sum('balance'), 2);
        $totalEquity = round($totalEquityPosted + $currentResult, 2);

        return response()->json([
            'success' => true,
            'message' => 'Balance sheet retrieved successfully',
            'data' => [
                'as_of' => $asOf,
                'assets' => ['total' => $totalAssets, 'accounts' => $assets],
                'liabilities' => ['total' => $totalLiabilities, 'accounts' => $liabilities],
                'equity' => [
                    'total' => $totalEquity,
                    'accounts' => $equity,
                    'posted_total' => $totalEquityPosted,
                    // Unclosed profit for the period, shown as its own equity line.
                    'current_period_result' => $currentResult,
                ],
                'totals' => [
                    'assets' => $totalAssets,
                    'liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
                ],
                'difference' => round($totalAssets - ($totalLiabilities + $totalEquity), 2),
                'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < self::EPSILON,
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** @return \Illuminate\Support\Collection<int,object> */
    private function movementsByType(array $types, string $fromDate, string $toDate)
    {
        return DB::table('ledger_accounts as a')
            ->leftJoin('journal_entry_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entry_headers as h', function ($join) use ($fromDate, $toDate) {
                $join->on('h.id', '=', 'l.journal_entry_header_id')
                    ->whereNull('h.deleted_at')
                    ->whereBetween('h.entry_date', [$fromDate, $toDate]);
            })
            ->whereIn('a.type', $types)
            ->selectRaw('a.id, a.code, a.name, a.type,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.debit END), 0) as debits,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.credit END), 0) as credits')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code')
            ->get();
    }

    /** Entries whose own lines do not add up — these break every report. */
    private function unbalancedEntries(string $fromDate, string $toDate): array
    {
        return JournalEntryHeader::query()
            ->whereBetween('entry_date', [$fromDate, $toDate])
            ->whereHas('lines')
            ->withSum('lines as sum_debit', 'debit')
            ->withSum('lines as sum_credit', 'credit')
            ->get()
            ->filter(fn ($h) => abs((float) $h->sum_debit - (float) $h->sum_credit) > self::EPSILON)
            ->map(fn ($h) => [
                'id' => $h->id,
                'entry_number' => $h->entry_number,
                'entry_date' => optional($h->entry_date)->toDateString(),
                'debit' => round((float) $h->sum_debit, 2),
                'credit' => round((float) $h->sum_credit, 2),
                'difference' => round((float) $h->sum_debit - (float) $h->sum_credit, 2),
            ])
            ->values()
            ->all();
    }

    /** @return array{0:string,1:string} */
    private function period(Request $request): array
    {
        return [
            $request->input('date_from', now()->startOfYear()->toDateString()),
            $request->input('date_to', now()->toDateString()),
        ];
    }
}
