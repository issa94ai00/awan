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

        $ledgerAccount->update(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? $ledgerAccount->is_active,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Ledger account updated successfully',
            'data' => $ledgerAccount,
        ]);
    }

    public function destroy(LedgerAccount $ledgerAccount): JsonResponse
    {
        $ledgerAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ledger account deleted successfully',
            'data' => null,
        ]);
    }
}
