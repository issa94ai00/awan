<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Opening and closing the periods the books are kept in.
 *
 * Closing a period is the only thing in the system that makes a date final.
 * Until one exists, every statement anyone prints describes a month that any
 * later posting can still change — a backdated invoice, a late stock count, a
 * hand-typed entry — with nothing to signal that it happened.
 *
 * Reopening is allowed, and recorded. Refusing it outright would push people
 * to work around the lock instead of through it, and the trail of who reopened
 * what is more useful than a door that cannot be opened.
 */
class AccountingPeriodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $periods = AccountingPeriod::with(['closedBy:id,name', 'reopenedBy:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (AccountingPeriod $period) => $this->present($period));

        return response()->json([
            'success' => true,
            'message' => 'Accounting periods retrieved successfully',
            'data' => [
                'periods' => $periods,
                // What today is subject to, so a screen can warn before someone
                // starts entering documents that will be refused.
                'today_is_closed' => AccountingPeriod::isClosed(now()->toDateString()),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:1000',
        ], [
            'end_date.after_or_equal' => 'تاريخ نهاية الفترة يجب ألا يسبق بدايتها',
        ]);

        // Overlapping periods would leave a date belonging to two of them, one
        // possibly closed and the other open, and the answer to "may I post
        // here" would depend on which row was read first.
        $overlap = AccountingPeriod::where('start_date', '<=', $validated['end_date'])
            ->where('end_date', '>=', $validated['start_date'])
            ->first();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'الفترة تتداخل مع فترة قائمة: '.$overlap->name,
                'data' => null,
            ], 422);
        }

        $period = AccountingPeriod::create($validated + ['status' => AccountingPeriod::STATUS_OPEN]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الفترة المحاسبية',
            'data' => $this->present($period),
        ], 201);
    }

    /**
     * Closes a period.
     *
     * Refuses while the period still holds an entry whose own lines do not add
     * up: closing over a corrupt entry freezes it into the reported figures,
     * and the difference then has to be chased in a month nobody may touch.
     */
    public function close(Request $request, AccountingPeriod $accountingPeriod): JsonResponse
    {
        if ($accountingPeriod->status === AccountingPeriod::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'الفترة مقفلة مسبقاً.',
                'data' => null,
            ], 422);
        }

        $unbalanced = $this->unbalancedCount($accountingPeriod);

        if ($unbalanced > 0) {
            return response()->json([
                'success' => false,
                'message' => "لا يمكن إقفال الفترة وفيها {$unbalanced} قيداً غير متوازن. راجعها من شاشة اليومية أولاً.",
                'data' => ['unbalanced_entries' => $unbalanced],
            ], 422);
        }

        $accountingPeriod->update([
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => auth()->id(),
            'notes' => $request->input('notes', $accountingPeriod->notes),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إقفال الفترة؛ لن تُقبل أي قيود بتواريخها.',
            'data' => $this->present($accountingPeriod->fresh(['closedBy', 'reopenedBy'])),
        ]);
    }

    /** Reopens a closed period, recording who did it and when. */
    public function reopen(Request $request, AccountingPeriod $accountingPeriod): JsonResponse
    {
        if ($accountingPeriod->status !== AccountingPeriod::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'الفترة مفتوحة أصلاً.',
                'data' => null,
            ], 422);
        }

        $accountingPeriod->update([
            'status' => AccountingPeriod::STATUS_OPEN,
            'reopened_at' => now(),
            'reopened_by' => auth()->id(),
            'notes' => $request->input('notes', $accountingPeriod->notes),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة فتح الفترة، وسُجّل ذلك باسمك وتاريخه.',
            'data' => $this->present($accountingPeriod->fresh(['closedBy', 'reopenedBy'])),
        ]);
    }

    /**
     * Deleting a period is only for one created by mistake. A closed period is
     * a statement that its months are final, and removing it silently unlocks
     * them.
     */
    public function destroy(AccountingPeriod $accountingPeriod): JsonResponse
    {
        if ($accountingPeriod->status === AccountingPeriod::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف فترة مقفلة. أعد فتحها أولاً إن كان إنشاؤها خطأً.',
                'data' => null,
            ], 422);
        }

        $accountingPeriod->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الفترة',
            'data' => null,
        ]);
    }

    /** @return array<string,mixed> */
    private function present(AccountingPeriod $period): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'status' => $period->status,
            'closed_at' => $period->closed_at?->toDateTimeString(),
            'closed_by' => $period->closedBy?->name,
            'reopened_at' => $period->reopened_at?->toDateTimeString(),
            'reopened_by' => $period->reopenedBy?->name,
            'notes' => $period->notes,
            // How much is being frozen, so closing is not a blind decision.
            'entry_count' => $this->entryCount($period),
        ];
    }

    /**
     * The range as plain dates.
     *
     * Passing the Carbon casts straight into a query serialises them with a
     * time, and a date compared against "2026-01-01 00:00:00" as text sorts
     * *before* it — so an entry on the first day of the period fell outside it.
     *
     * @return array{0:string,1:string}
     */
    private function range(AccountingPeriod $period): array
    {
        return [$period->start_date->toDateString(), $period->end_date->toDateString()];
    }

    private function entryCount(AccountingPeriod $period): int
    {
        return (int) DB::table('journal_entry_headers')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(entry_date)'), $this->range($period))
            ->count();
    }

    /** Entries inside the period whose debits and credits disagree. */
    private function unbalancedCount(AccountingPeriod $period): int
    {
        return DB::table('journal_entry_headers as h')
            ->join('journal_entry_lines as l', 'l.journal_entry_header_id', '=', 'h.id')
            ->whereNull('h.deleted_at')
            ->whereBetween(DB::raw('DATE(h.entry_date)'), $this->range($period))
            // Only the grouped key is selected: the default `select *` is
            // rejected outright by MySQL under ONLY_FULL_GROUP_BY, which SQLite
            // accepts happily — so the tests would pass and closing a period
            // would fail in production.
            ->select('h.id')
            ->groupBy('h.id')
            ->havingRaw('ABS(COALESCE(SUM(l.debit),0) - COALESCE(SUM(l.credit),0)) > 0.005')
            ->get()
            ->count();
    }
}
