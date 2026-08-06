<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingReportController extends Controller
{
    public function trialBalance(): JsonResponse
    {
        $accounts = LedgerAccount::with(['journalEntryLines' => fn ($q) => $q->whereHas('header')])
            ->get()
            ->map(function ($account) {
                $debits = $account->journalEntryLines->sum('debit');
                $credits = $account->journalEntryLines->sum('credit');

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'debits' => (float) $debits,
                    'credits' => (float) $credits,
                    'balance' => (float) $account->balance,
                ];
            });

        $totals = $accounts->reduce(function ($carry, $account) {
            $carry['debits'] += $account['debits'];
            $carry['credits'] += $account['credits'];
            return $carry;
        }, ['debits' => 0.0, 'credits' => 0.0]);

        return response()->json([
            'success' => true,
            'message' => 'Trial balance retrieved successfully',
            'data' => [
                'accounts' => $accounts,
                'totals' => $totals,
                'is_balanced' => round($totals['debits'], 2) === round($totals['credits'], 2),
            ],
        ]);
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        $fromDate = $request->input('date_from', now()->startOfMonth()->toDateString());
        $toDate = $request->input('date_to', now()->toDateString());

        $lines = JournalEntryLine::with(['ledgerAccount', 'header'])
            ->whereHas('header', fn ($q) => $q->whereBetween('entry_date', [$fromDate, $toDate]))
            ->whereHas('ledgerAccount', fn ($q) => $q->whereIn('type', ['revenue', 'expense']))
            ->get();

        $revenueLines = $lines->filter(fn ($line) => $line->ledgerAccount->type === 'revenue');
        $expenseLines = $lines->filter(fn ($line) => $line->ledgerAccount->type === 'expense');

        $revenueByAccount = $revenueLines->groupBy('account_id')->map(function ($group) {
            $account = $group->first()->ledgerAccount;
            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'amount' => (float) ($group->sum('credit') - $group->sum('debit')),
            ];
        })->values();

        $expenseByAccount = $expenseLines->groupBy('account_id')->map(function ($group) {
            $account = $group->first()->ledgerAccount;
            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'amount' => (float) ($group->sum('debit') - $group->sum('credit')),
            ];
        })->values();

        $totalRevenue = (float) $revenueByAccount->sum('amount');
        $totalExpense = (float) $expenseByAccount->sum('amount');

        return response()->json([
            'success' => true,
            'message' => 'Income statement retrieved successfully',
            'data' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'revenue' => ['total' => $totalRevenue, 'accounts' => $revenueByAccount],
                'expenses' => ['total' => $totalExpense, 'accounts' => $expenseByAccount],
                'net_income' => $totalRevenue - $totalExpense,
            ],
        ]);
    }

    public function balanceSheet(): JsonResponse
    {
        $accounts = LedgerAccount::where('is_active', true)->get();

        $mapAccounts = fn ($group) => $group->map(fn ($a) => [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name,
            'balance' => (float) $a->balance,
        ])->values();

        $assets = $mapAccounts($accounts->where('type', 'asset'));
        $liabilities = $mapAccounts($accounts->where('type', 'liability'));
        $equity = $mapAccounts($accounts->where('type', 'equity'));

        $totalAssets = (float) $assets->sum('balance');
        $totalLiabilities = (float) $liabilities->sum('balance');
        $totalEquity = (float) $equity->sum('balance');

        return response()->json([
            'success' => true,
            'message' => 'Balance sheet retrieved successfully',
            'data' => [
                'as_of' => now()->toDateString(),
                'assets' => ['total' => $totalAssets, 'accounts' => $assets],
                'liabilities' => ['total' => $totalLiabilities, 'accounts' => $liabilities],
                'equity' => ['total' => $totalEquity, 'accounts' => $equity],
                'is_balanced' => round($totalAssets, 2) === round($totalLiabilities + $totalEquity, 2),
            ],
        ]);
    }
}
