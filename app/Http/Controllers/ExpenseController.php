<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $expenses = Expense::with(['invoice', 'customer', 'creator'])->latest()->get();
        return response()->json(['data' => $expenses]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|in:shipping,packaging,handling,other',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
            'invoice_id' => 'nullable|exists:invoices,id',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $expense = Expense::create([
            'expense_number' => 'EXP-' . str_pad(Expense::count() + 1, 6, '0', STR_PAD_LEFT),
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'category' => $validated['category'] ?? 'other',
            'expense_date' => $validated['expense_date'],
            'notes' => $validated['notes'] ?? null,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'status' => 'pending',
            'created_by' => auth()->id(),
            'currency' => 'SAR',
            'exchange_rate' => 1.0000,
        ]);

        return response()->json(['data' => $expense], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $expense = Expense::with(['invoice', 'customer', 'creator'])->findOrFail($id);
        return response()->json(['data' => $expense]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        $validated = $request->validate([
            'description' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|string|in:shipping,packaging,handling,other',
            'expense_date' => 'sometimes|date',
            'notes' => 'nullable|string',
            'status' => 'sometimes|string|in:pending,approved,rejected',
        ]);

        $expense->update($validated);

        return response()->json(['data' => $expense]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return response()->json(['message' => 'Expense deleted successfully']);
    }
}
