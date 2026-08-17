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
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:ledger_accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'balance' => 'nullable|numeric',
            'is_active' => 'sometimes|boolean',
        ]);

        $account = LedgerAccount::create(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? true,
            'balance' => $validated['balance'] ?? 0,
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
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:ledger_accounts,code,' . $ledgerAccount->id,
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'balance' => 'nullable|numeric',
            'is_active' => 'sometimes|boolean',
        ]);

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
}
