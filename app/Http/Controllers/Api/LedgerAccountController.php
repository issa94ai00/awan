<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LedgerAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LedgerAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LedgerAccount::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $accounts = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Ledger accounts retrieved successfully',
            'data' => [
                'accounts' => $accounts->items(),
                'pagination' => [
                    'current_page' => $accounts->currentPage(),
                    'last_page' => $accounts->lastPage(),
                    'per_page' => $accounts->perPage(),
                    'total' => $accounts->total(),
                    'has_more_pages' => $accounts->hasMorePages(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        if ($refusal = $this->parentRefusal($validated)) {
            return $refusal;
        }

        $account = LedgerAccount::create(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? true,
            // Starts at zero and moves only through posted entries. An opening
            // figure typed here had no entry behind it, so the trial balance
            // stopped balancing by exactly that amount.
            'balance' => 0,
            // The books are kept in one currency, so an account opened by hand
            // takes it rather than being left blank or guessing.
            'currency' => base_currency_code(),
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Ledger account created successfully',
            'data' => $account,
        ], 201);
    }

    public function show(LedgerAccount $ledgerAccount): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Ledger account retrieved successfully',
            'data' => $ledgerAccount,
        ]);
    }

    public function update(Request $request, LedgerAccount $ledgerAccount): JsonResponse
    {
        $validated = $request->validate($this->rules($ledgerAccount));

        if ($refusal = $this->parentRefusal($validated, $ledgerAccount)) {
            return $refusal;
        }

        if ($validated['type'] !== $ledgerAccount->type && $ledgerAccount->journalEntryLines()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تغيير نوع الحساب بعد ترحيل قيود محاسبية عليه — سيؤدي ذلك إلى تشويه الأرصدة عند تعديل أو حذف تلك القيود لاحقاً',
            ], 422);
        }

        $ledgerAccount->update(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? $ledgerAccount->is_active,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Ledger account updated successfully',
            'data' => $ledgerAccount,
        ]);
    }

    /**
     * Removes an account from the chart — only if nothing depends on it.
     *
     * This used to delete whatever it was given. Three things could go wrong,
     * and each was silent:
     *
     *  - **An account with entries.** The database now refuses this outright
     *    (the foreign key restricts), so the caller saw a 500 with a driver
     *    message instead of an explanation. Its balance is also part of every
     *    statement already printed.
     *  - **An account holding a posting role.** Deleting the one that answers
     *    to `cash` does not fail here; it fails the next time anybody records a
     *    payment, with an error naming a role rather than the account somebody
     *    removed.
     *  - **A parent.** Its children are left pointing at nothing, and the chart
     *    loses the branch they were grouped under.
     */
    public function destroy(LedgerAccount $ledgerAccount): JsonResponse
    {
        $refusal = match (true) {
            $ledgerAccount->journalEntryLines()->exists() =>
                'لا يمكن حذف حساب رُحّلت عليه قيود. عطّله بدل حذفه إن لم يعد مستخدماً.',

            (bool) $ledgerAccount->posting_role =>
                'هذا الحساب يحمل دور الترحيل «'.$ledgerAccount->posting_role.'»، ويعتمد عليه النظام في ترحيل المستندات. أسنِد الدور لحساب آخر أولاً.',

            (bool) $ledgerAccount->is_system =>
                'حساب نظامي لا يُحذف.',

            LedgerAccount::where('parent_id', $ledgerAccount->id)->exists() =>
                'لا يمكن حذف حساب رئيسي تتفرع عنه حسابات. انقل الحسابات الفرعية أو احذفها أولاً.',

            default => null,
        };

        if ($refusal) {
            return response()->json([
                'success' => false,
                'message' => $refusal,
                'data' => null,
            ], 422);
        }

        $ledgerAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ledger account deleted successfully',
            'data' => null,
        ]);
    }

    /**
     * The five account types, stored lowercase.
     *
     * `type` was any string, and the chart screen offered "Asset", "Liability"
     * and so on. Everything that reads the type compares it to lowercase names
     * — `signedDelta` strictly — so an account created from that screen was
     * treated as credit-normal and every posting moved its balance the wrong
     * way. It is lowercased before validation and limited to the five.
     *
     * `balance` is deliberately absent: it is the posting engine's running
     * total. The edit form used to send back the figure it had loaded, which
     * overwrote anything posted while the form sat open.
     */
    private function rules(?LedgerAccount $account = null): array
    {
        request()->merge(['type' => strtolower(trim((string) request('type')))]);

        return [
            'code' => 'required|string|max:50|unique:ledger_accounts,code'.($account ? ','.$account->id : ''),
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|integer|exists:ledger_accounts,id',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * A parent must be of the same type and must not sit below the account.
     *
     * A liability grouped under assets would be counted with the assets by
     * anything that rolls a branch up, and a parent that is its own
     * descendant makes the tree a loop that never finishes walking.
     */
    private function parentRefusal(array $validated, ?LedgerAccount $account = null): ?JsonResponse
    {
        if (empty($validated['parent_id'])) {
            return null;
        }

        $parent = LedgerAccount::find($validated['parent_id']);

        $message = match (true) {
            $parent->type !== $validated['type'] =>
                'يجب أن يكون الحساب الرئيسي من نوع الحساب نفسه.',
            $account && $this->isSelfOrDescendant($parent, $account) =>
                'لا يمكن جعل الحساب تابعاً لنفسه أو لأحد حساباته الفرعية.',
            default => null,
        };

        return $message
            ? response()->json(['success' => false, 'message' => $message, 'data' => null], 422)
            : null;
    }

    private function isSelfOrDescendant(LedgerAccount $candidate, LedgerAccount $account): bool
    {
        $seen = [];

        for ($node = $candidate; $node; $node = $node->parent_id ? LedgerAccount::find($node->parent_id) : null) {
            if ($node->id === $account->id || isset($seen[$node->id])) {
                return true;
            }
            $seen[$node->id] = true;
        }

        return false;
    }
}
