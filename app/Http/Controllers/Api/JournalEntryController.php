<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JournalEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = JournalEntry::with('ledgerAccount');

        if ($request->filled('ledger_account_id')) {
            $query->where('ledger_account_id', $request->ledger_account_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }

        $entries = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Journal entries retrieved successfully',
            'data' => [
                'entries' => $entries->items(),
                'pagination' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'per_page' => $entries->perPage(),
                    'total' => $entries->total(),
                    'has_more_pages' => $entries->hasMorePages(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entry_date' => 'required|date',
            'ledger_account_id' => 'required|exists:ledger_accounts,id',
            'description' => 'nullable|string|max:1000',
            'debit' => 'nullable|numeric|min:0',
            'credit' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:255',
        ]);

        $validated['debit'] = $validated['debit'] ?? 0;
        $validated['credit'] = $validated['credit'] ?? 0;
        $validated['created_by'] = auth()->id();

        if ($validated['debit'] === 0 && $validated['credit'] === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Either debit or credit must be greater than zero',
            ], 422);
        }

        $entry = JournalEntry::create($validated);

        if ($entry->ledgerAccount) {
            $balanceChange = $entry->debit - $entry->credit;
            $entry->ledgerAccount->increment('balance', $balanceChange);
        }

        return response()->json([
            'success' => true,
            'message' => 'Journal entry created successfully',
            'data' => $entry->load('ledgerAccount'),
        ], 201);
    }

    public function trialBalance(): JsonResponse
    {
        $accounts = LedgerAccount::with(['journalEntries'])
            ->get()
            ->map(function ($account) {
                $debits = $account->journalEntries->sum('debit');
                $credits = $account->journalEntries->sum('credit');

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
            ],
        ]);
    }
}
