<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Operating expenses, and their life in the ledger.
 *
 * An expense is a financial document like any other here: it is recognised
 * when it is incurred, it is corrected by reversing and re-posting, and it
 * cannot be quietly removed from a period that has been reported on.
 */
class ExpenseController extends Controller
{
    public function __construct(private LedgerPostingService $ledger)
    {
    }

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

        try {
            // The expense and its entry are one transaction. This used to catch
            // the posting failure, log it and answer 201 with the reason in an
            // `accounting_warning` field that no screen displays — so a cost
            // the books never heard of looked, to whoever entered it, exactly
            // like one that posted.
            $expense = DB::transaction(function () use ($validated) {
                $expense = Expense::create([
                    // Derived from the last id: counting hands out a number that
                    // is already taken as soon as any expense is deleted.
                    'expense_number' => 'EXP-'.str_pad((string) (((int) Expense::max('id')) + 1), 6, '0', STR_PAD_LEFT),
                    'description' => $validated['description'],
                    'amount' => $validated['amount'],
                    'category' => $validated['category'] ?? 'other',
                    'expense_date' => $validated['expense_date'],
                    'notes' => $validated['notes'] ?? null,
                    'invoice_id' => $validated['invoice_id'] ?? null,
                    'customer_id' => $validated['customer_id'] ?? null,
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                    'currency' => base_currency_code(),
                    'exchange_rate' => 1.0000,
                ]);

                $this->ledger->postExpense($expense);

                return $expense;
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'تعذّر ترحيل قيد المصروف: '.$e->getMessage(),
                'data' => null,
            ], 422);
        }

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
     *
     * Changing what an expense cost, when it happened, or which account it
     * belongs to changes the entry behind it — so the original is reversed and
     * the corrected figures are posted under a new key. Both stay in the
     * journal, which is how every other document here is corrected.
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
            'status' => 'sometimes|string|in:pending,approved,rejected,paid',
        ]);

        // Only fields that change the entry force a restatement; a corrected
        // spelling in the description does not need to touch the books.
        $affectsLedger = collect(['amount', 'category', 'expense_date', 'status'])
            ->contains(fn ($field) => array_key_exists($field, $validated)
                && (string) $validated[$field] !== (string) $expense->{$field});

        try {
            DB::transaction(function () use ($expense, $validated, $affectsLedger) {
                $expense->update($validated);

                if ($affectsLedger) {
                    $this->ledger->reverseFor($expense->postingKey());
                    $this->ledger->postExpense(
                        $expense->refresh(),
                        $expense->postingKey().':corrected:'.now()->getTimestamp()
                    );
                }
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'تعذّر تصحيح قيد المصروف: '.$e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json(['data' => $expense->refresh()]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * The entry is reversed rather than deleted with the document: an expense
     * that vanished silently kept its cost in the income statement of a period
     * that had already been reported on, with no document left to explain it.
     */
    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        try {
            DB::transaction(function () use ($expense) {
                $this->ledger->reverseFor($expense->postingKey());
                $expense->delete();
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'تعذّر عكس قيد المصروف: '.$e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json(['message' => 'Expense deleted successfully']);
    }
}
