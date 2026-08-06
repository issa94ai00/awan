<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntryHeader;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = JournalEntryHeader::with('lines.ledgerAccount');

        if ($request->filled('ledger_account_id')) {
            $accountId = $request->ledger_account_id;
            $query->whereHas('lines', fn ($q) => $q->where('account_id', $accountId));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }

        $entries = $query->latest('entry_date')->latest('id')->paginate($request->input('per_page', 20));

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

    public function show(JournalEntryHeader $journalEntry): JsonResponse
    {
        $journalEntry->load('lines.ledgerAccount');

        return response()->json([
            'success' => true,
            'message' => 'Journal entry retrieved successfully',
            'data' => $journalEntry,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateHeaderAndLines($request);

        $entry = DB::transaction(function () use ($validated) {
            $entryNumber = $this->nextEntryNumber();

            $header = JournalEntryHeader::create([
                'entry_number' => $entryNumber,
                'entry_date' => $validated['entry_date'],
                'description' => $validated['description'] ?? null,
                'total_debit' => $validated['total_debit'],
                'total_credit' => $validated['total_credit'],
                'currency' => $validated['currency'] ?? 'SAR',
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $this->createLinesAndApplyBalances($header, $validated['lines']);

            return $header;
        });

        return response()->json([
            'success' => true,
            'message' => 'Journal entry created successfully',
            'data' => $entry->load('lines.ledgerAccount'),
        ], 201);
    }

    public function update(Request $request, JournalEntryHeader $journalEntry): JsonResponse
    {
        $validated = $this->validateHeaderAndLines($request);

        $entry = DB::transaction(function () use ($validated, $journalEntry) {
            $journalEntry = JournalEntryHeader::whereKey($journalEntry->id)->lockForUpdate()->firstOrFail();

            // Reverse the old lines' balance impact before removing them.
            $oldLines = JournalEntryLine::with('ledgerAccount')->where('journal_entry_header_id', $journalEntry->id)->get();
            foreach ($oldLines as $line) {
                $delta = LedgerAccount::signedDelta($line->ledgerAccount->type, (float) $line->debit, (float) $line->credit);
                $line->ledgerAccount->increment('balance', -$delta);
            }
            JournalEntryLine::where('journal_entry_header_id', $journalEntry->id)->delete();

            $journalEntry->update([
                'entry_date' => $validated['entry_date'],
                'description' => $validated['description'] ?? null,
                'total_debit' => $validated['total_debit'],
                'total_credit' => $validated['total_credit'],
                'currency' => $validated['currency'] ?? $journalEntry->currency,
            ]);

            $this->createLinesAndApplyBalances($journalEntry, $validated['lines']);

            return $journalEntry;
        });

        return response()->json([
            'success' => true,
            'message' => 'Journal entry updated successfully',
            'data' => $entry->load('lines.ledgerAccount'),
        ]);
    }

    public function destroy(JournalEntryHeader $journalEntry): JsonResponse
    {
        DB::transaction(function () use ($journalEntry) {
            $journalEntry = JournalEntryHeader::whereKey($journalEntry->id)->lockForUpdate()->firstOrFail();

            $lines = JournalEntryLine::with('ledgerAccount')->where('journal_entry_header_id', $journalEntry->id)->get();
            foreach ($lines as $line) {
                $delta = LedgerAccount::signedDelta($line->ledgerAccount->type, (float) $line->debit, (float) $line->credit);
                $line->ledgerAccount->increment('balance', -$delta);
            }

            $journalEntry->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Journal entry deleted successfully',
            'data' => null,
        ]);
    }

    /**
     * @return array{entry_date: string, description: ?string, currency: ?string, lines: array, total_debit: string, total_credit: string}
     */
    private function validateHeaderAndLines(Request $request): array
    {
        $validated = $request->validate([
            'entry_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'currency' => 'nullable|string|max:10',
            'lines' => 'required|array|min:2',
            'lines.*.ledger_account_id' => 'required|integer|exists:ledger_accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.employee_id' => 'nullable|integer|exists:employees,id',
        ], [
            'lines.required' => 'يجب إدخال سطرين على الأقل للقيد',
            'lines.min' => 'يجب إدخال سطرين على الأقل للقيد',
            'lines.*.ledger_account_id.required' => 'يجب اختيار الحساب لكل سطر',
        ]);

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($validated['lines'] as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                throw ValidationException::withMessages([
                    'lines' => 'كل سطر يجب أن يحتوي على مدين أو دائن فقط، وليس كليهما أو لا شيء',
                ]);
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw ValidationException::withMessages([
                'lines' => "القيد غير متوازن: إجمالي المدين ({$totalDebit}) لا يساوي إجمالي الدائن ({$totalCredit})",
            ]);
        }

        $validated['total_debit'] = number_format($totalDebit, 2, '.', '');
        $validated['total_credit'] = number_format($totalCredit, 2, '.', '');

        return $validated;
    }

    private function createLinesAndApplyBalances(JournalEntryHeader $header, array $lines): void
    {
        foreach ($lines as $line) {
            $account = LedgerAccount::findOrFail($line['ledger_account_id']);
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            JournalEntryLine::create([
                'journal_entry_header_id' => $header->id,
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
                'employee_id' => $line['employee_id'] ?? null,
            ]);

            $delta = LedgerAccount::signedDelta($account->type, $debit, $credit);
            $account->increment('balance', $delta);
        }
    }

    /**
     * Must be called from within an existing DB::transaction() so the lock holds
     * until the new header is committed (matches RmaController's number-generation pattern).
     */
    private function nextEntryNumber(): string
    {
        $last = JournalEntryHeader::withTrashed()->orderByDesc('id')->lockForUpdate()->first();
        $nextId = $last ? $last->id + 1 : 1;

        return 'JE-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }
}
