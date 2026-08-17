<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntryHeader;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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

    /**
     * Records a hand-typed entry — through the same engine as everything else.
     *
     * This method used to write its own header, its own lines and its own
     * balance updates. That made it a second implementation of rules the
     * posting service already enforces: balance checking, entry numbering,
     * account balances, and — added twice, once here and once there — the
     * closed-period guard. Every rule added to posting had to be remembered in
     * two places, and the one entry a person types by hand was the likeliest
     * place to forget.
     *
     * The key is drawn before the entry exists rather than after, because the
     * service needs one to write with. It is unique per entry either way.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateHeaderAndLines($request);

        try {
            $entry = app(LedgerPostingService::class)->post(
                key: 'manual:'.Str::uuid(),
                date: $validated['entry_date'],
                description: $validated['description'] ?? 'قيد يدوي',
                lines: array_map(fn ($line) => [
                    'account_id' => (int) $line['ledger_account_id'],
                    'debit' => (float) ($line['debit'] ?? 0),
                    'credit' => (float) ($line['credit'] ?? 0),
                    'description' => $line['description'] ?? null,
                    'employee_id' => $line['employee_id'] ?? null,
                ], $validated['lines']),
                module: 'manual',
                currency: $validated['currency'] ?? null,
            );
        } catch (RuntimeException $e) {
            // Covers the closed period and an unbalanced entry alike: both are
            // refusals from the engine, and both are explained by its message.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        if (! $entry) {
            return response()->json([
                'success' => false,
                'message' => 'القيد لا يحتوي على سطرين فعليين على الأقل.',
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Journal entry created successfully',
            'data' => $entry->load('lines.ledgerAccount'),
        ], 201);
    }

    /**
     * A posted entry is not editable, and this is where that rule was broken.
     *
     * `LedgerPostingService` guards the books on three rules — balanced or
     * nothing, posted exactly once, never rewritten — and every document goes
     * through it. This endpoint went around all three: it deleted the lines of
     * a posted entry, unwound their effect on the account balances and wrote
     * new ones in their place, under the same entry number and with no record
     * that anything had changed. A trial balance printed before the edit and
     * one printed after disagreed, and nothing in the system could say why.
     *
     * Correction is a reversal followed by a fresh entry, both of which stay
     * in the journal. `reverse` below does the first half.
     */
    public function update(Request $request, JournalEntryHeader $journalEntry): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكن تعديل قيد مُرحَّل. سجّل قيداً عكسياً له ثم أدخل القيد الصحيح، ليبقى أثر التصحيح في الدفاتر.',
            'data' => null,
        ], 422);
    }

    /**
     * Deleting a posted entry is refused for the same reason editing is: the
     * period it belongs to has already been reported on, and a journal that
     * loses entries cannot be reconciled against anything.
     */
    public function destroy(JournalEntryHeader $journalEntry): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكن حذف قيد مُرحَّل. استخدم العكس المحاسبي؛ يبقى القيد الأصلي ويظهر بجانبه قيد يلغي أثره.',
            'data' => null,
        ], 422);
    }

    /**
     * Writes the mirror image of an entry and marks the original reversed.
     *
     * The supported way to undo a posting: both entries stay in the journal,
     * so the account balances return to where they were while the history of
     * how they got there survives.
     */
    public function reverse(Request $request, JournalEntryHeader $journalEntry): JsonResponse
    {
        $validated = $request->validate([
            'entry_date' => 'nullable|date',
        ]);

        if ($journalEntry->status === 'reversed') {
            return response()->json([
                'success' => false,
                'message' => 'هذا القيد معكوس مسبقاً.',
                'data' => null,
            ], 422);
        }

        $reversal = app(LedgerPostingService::class)->reverseEntry(
            $journalEntry,
            $validated['entry_date'] ?? null
        );

        if (! $reversal) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر إنشاء القيد العكسي.',
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم ترحيل قيد عكسي رقم '.$reversal->entry_number,
            'data' => $reversal->load('lines.ledgerAccount'),
        ], 201);
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

}
