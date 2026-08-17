<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Holding the bank account against the bank's own statement.
 *
 * The bank balance is the one figure in the books with an independent witness,
 * and nothing here ever asked it. A difference between the two is usually just
 * timing — a cheque written before month end and cashed after it — but without
 * a reconciliation there is no way to tell that apart from a payment entered
 * twice, a transfer that never arrived, or a bank charge nobody recorded.
 */
class BankReconciliationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BankReconciliation::with(['account:id,code,name', 'completedBy:id,name']);

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        $reconciliations = $query->orderByDesc('statement_date')->orderByDesc('id')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Bank reconciliations retrieved successfully',
            'data' => [
                'reconciliations' => collect($reconciliations->items())->map(fn ($row) => array_merge(
                    $row->toArray(),
                    ['summary' => $row->summary()]
                ))->values(),
                // The accounts worth reconciling: the ones an outside statement
                // exists for.
                'accounts' => LedgerAccount::whereIn('posting_role', ['bank', 'cash'])
                    ->get(['id', 'code', 'name', 'posting_role']),
                'pagination' => [
                    'current_page' => $reconciliations->currentPage(),
                    'last_page' => $reconciliations->lastPage(),
                    'per_page' => $reconciliations->perPage(),
                    'total' => $reconciliations->total(),
                    'has_more_pages' => $reconciliations->hasMorePages(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:ledger_accounts,id',
            'statement_date' => 'required|date',
            'statement_balance' => 'required|numeric',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Two open reconciliations on one account would let the same movement
        // be cleared in both, and each would prove itself against a different
        // set of outstanding items.
        $existing = BankReconciliation::where('account_id', $validated['account_id'])
            ->where('status', BankReconciliation::STATUS_OPEN)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'توجد تسوية مفتوحة على هذا الحساب ('.$existing->reference.'). أكملها أو احذفها أولاً.',
                'data' => null,
            ], 422);
        }

        $reconciliation = BankReconciliation::create($validated + [
            'reference' => 'BR-'.str_pad((string) (((int) BankReconciliation::max('id')) + 1), 5, '0', STR_PAD_LEFT),
            'status' => BankReconciliation::STATUS_OPEN,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم فتح تسوية بنكية',
            'data' => $this->present($reconciliation),
        ], 201);
    }

    /** The working sheet: every movement, cleared or not, and the arithmetic. */
    public function show(BankReconciliation $bankReconciliation): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Bank reconciliation retrieved successfully',
            'data' => $this->present($bankReconciliation),
        ]);
    }

    /**
     * Marks a movement as seen by the bank, or takes the mark back.
     *
     * This is the whole act of reconciling: going down the statement and
     * ticking off what matches.
     */
    public function toggleLine(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        if ($bankReconciliation->status === BankReconciliation::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'التسوية مكتملة — أعد فتحها لتعديل ما صُرف.',
                'data' => null,
            ], 422);
        }

        $validated = $request->validate([
            'line_id' => 'required|integer|exists:journal_entry_lines,id',
        ]);

        $line = JournalEntryLine::findOrFail($validated['line_id']);

        // A line from another account would prove nothing about this one.
        if ((int) $line->account_id !== (int) $bankReconciliation->account_id) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الحركة لا تخص الحساب محل التسوية.',
                'data' => null,
            ], 422);
        }

        if ($bankReconciliation->clearedLines()->where('journal_entry_lines.id', $line->id)->exists()) {
            $bankReconciliation->clearedLines()->detach($line->id);
        } else {
            $bankReconciliation->clearedLines()->attach($line->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الحركة',
            'data' => $this->present($bankReconciliation->refresh()),
        ]);
    }

    /**
     * Closes a reconciliation — only if it actually reconciles.
     *
     * A completed reconciliation is a statement that every difference between
     * the books and the bank is explained by timing. Completing one that does
     * not balance would make that claim falsely, and the unexplained remainder
     * would never be looked for again.
     */
    public function complete(BankReconciliation $bankReconciliation): JsonResponse
    {
        if ($bankReconciliation->status === BankReconciliation::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'التسوية مكتملة مسبقاً.',
                'data' => null,
            ], 422);
        }

        $summary = $bankReconciliation->summary();

        if (! $summary['is_reconciled']) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'لا يمكن إقفال تسوية بفارق %s. الفارق ليس توقيتاً — راجع حركة ناقصة أو مكررة في أحد السجلين.',
                    number_format($summary['difference'], 2)
                ),
                'data' => ['summary' => $summary],
            ], 422);
        }

        $bankReconciliation->update([
            'status' => BankReconciliation::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تمت التسوية: كل فارق بين الدفاتر والبنك مفسَّر بالتوقيت.',
            'data' => $this->present($bankReconciliation->refresh()),
        ]);
    }

    /** Reopens a completed reconciliation so its ticks can be corrected. */
    public function reopen(BankReconciliation $bankReconciliation): JsonResponse
    {
        if ($bankReconciliation->status !== BankReconciliation::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'التسوية مفتوحة أصلاً.',
                'data' => null,
            ], 422);
        }

        $bankReconciliation->update([
            'status' => BankReconciliation::STATUS_OPEN,
            'completed_at' => null,
            'completed_by' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'أُعيد فتح التسوية',
            'data' => $this->present($bankReconciliation->refresh()),
        ]);
    }

    /** A completed reconciliation is a record; only an open one is removable. */
    public function destroy(BankReconciliation $bankReconciliation): JsonResponse
    {
        if ($bankReconciliation->status === BankReconciliation::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'لا تُحذف تسوية مكتملة. أعد فتحها أولاً إن كانت خاطئة.',
                'data' => null,
            ], 422);
        }

        $bankReconciliation->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التسوية',
            'data' => null,
        ]);
    }

    /** @return array<string,mixed> */
    private function present(BankReconciliation $reconciliation): array
    {
        $reconciliation->loadMissing(['account:id,code,name', 'completedBy:id,name']);

        return array_merge($reconciliation->toArray(), [
            'movements' => $reconciliation->movements()->values(),
            'summary' => $reconciliation->summary(),
        ]);
    }
}
