<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        if (in_array($request->input('status'), [BankReconciliation::STATUS_OPEN, BankReconciliation::STATUS_COMPLETED], true)) {
            $query->where('status', $request->input('status'));
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
                'accounts' => $this->accounts(),
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

        if ($refusal = $this->refuseClearedEarlier($bankReconciliation, [$line->id])) {
            return $refusal;
        }

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
     * Ticks several movements at once, or takes the ticks back.
     *
     * The explicit `cleared` makes it safe to repeat: two clicks sent close
     * together land where the reader left them rather than flipping twice.
     */
    public function setLines(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        if ($bankReconciliation->status === BankReconciliation::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'التسوية مكتملة — أعد فتحها لتعديل ما صُرف.',
                'data' => null,
            ], 422);
        }

        $validated = $request->validate([
            'line_ids' => 'required|array|min:1|max:2000',
            'line_ids.*' => 'integer',
            'cleared' => 'required|boolean',
        ]);

        $ids = collect($validated['line_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $movements = $bankReconciliation->movements()->keyBy('id');

        // Only this account's movements up to the statement date belong here.
        if ($ids->contains(fn ($id) => ! $movements->has($id))) {
            return response()->json([
                'success' => false,
                'message' => 'بعض الحركات لا تخص الحساب محل التسوية أو تقع بعد تاريخ الكشف.',
                'data' => null,
            ], 422);
        }

        if ($refusal = $this->refuseClearedEarlier($bankReconciliation, $ids->all())) {
            return $refusal;
        }

        if ($validated['cleared']) {
            $bankReconciliation->clearedLines()->syncWithoutDetaching($ids->all());
        } else {
            $bankReconciliation->clearedLines()->detach($ids->all());
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الحركات',
            'data' => $this->present($bankReconciliation->refresh()),
        ]);
    }

    /**
     * Corrects the statement a reconciliation is held against.
     *
     * A mistyped balance used to mean deleting the sheet and ticking every
     * movement again.
     */
    public function update(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        if ($bankReconciliation->status === BankReconciliation::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'التسوية مكتملة — أعد فتحها لتعديلها.',
                'data' => null,
            ], 422);
        }

        $validated = $request->validate([
            'statement_date' => 'sometimes|required|date',
            'statement_balance' => 'sometimes|required|numeric',
            'notes' => 'nullable|string|max:1000',
        ]);

        $bankReconciliation->update($validated);

        // A statement date moved earlier leaves ticks on movements the new
        // statement cannot show; they would be counted as cleared by a bank
        // that has not seen them.
        $inRange = $bankReconciliation->movements()->pluck('id')->all();
        $stale = $bankReconciliation->clearedLines()->pluck('journal_entry_lines.id')->diff($inRange);

        if ($stale->isNotEmpty()) {
            $bankReconciliation->clearedLines()->detach($stale->all());
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الكشف',
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

        // Same rule as opening one: two open sheets on an account could clear
        // the same movement twice.
        $open = BankReconciliation::where('account_id', $bankReconciliation->account_id)
            ->where('status', BankReconciliation::STATUS_OPEN)
            ->first();

        if ($open) {
            return response()->json([
                'success' => false,
                'message' => 'توجد تسوية مفتوحة على هذا الحساب ('.$open->reference.'). أكملها أو احذفها أولاً.',
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

    /**
     * The accounts worth reconciling — the ones an outside statement exists
     * for — with where each one stands, so the screen can offer to continue
     * an open sheet instead of refusing a second one after the form is filled.
     *
     * @return Collection<int,array<string,mixed>>
     */
    private function accounts()
    {
        $open = BankReconciliation::where('status', BankReconciliation::STATUS_OPEN)
            ->get(['id', 'reference', 'account_id', 'statement_date'])
            ->keyBy('account_id');

        $lastCompleted = BankReconciliation::where('status', BankReconciliation::STATUS_COMPLETED)
            ->selectRaw('account_id, MAX(statement_date) as last_date')
            ->groupBy('account_id')
            ->pluck('last_date', 'account_id');

        return LedgerAccount::whereIn('posting_role', ['bank', 'cash'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'posting_role'])
            ->map(fn ($account) => $account->toArray() + [
                'open_reconciliation' => ($row = $open->get($account->id)) ? [
                    'id' => $row->id,
                    'reference' => $row->reference,
                    'statement_date' => $row->statement_date?->toDateString(),
                ] : null,
                'last_reconciled_date' => ($date = $lastCompleted->get($account->id)) ? substr((string) $date, 0, 10) : null,
            ])
            ->values();
    }

    /** Movements an earlier completed reconciliation already proved are not this sheet's to change. */
    private function refuseClearedEarlier(BankReconciliation $reconciliation, array $lineIds): ?JsonResponse
    {
        $earlier = DB::table('bank_reconciliation_lines as rl')
            ->join('bank_reconciliations as r', 'r.id', '=', 'rl.bank_reconciliation_id')
            ->where('r.account_id', $reconciliation->account_id)
            ->where('r.status', BankReconciliation::STATUS_COMPLETED)
            ->where('r.id', '!=', $reconciliation->id)
            ->whereIn('rl.journal_entry_line_id', $lineIds)
            ->value('r.reference');

        if (! $earlier) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'هذه الحركة صُرفت في تسوية سابقة ('.$earlier.').',
            'data' => null,
        ], 422);
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
