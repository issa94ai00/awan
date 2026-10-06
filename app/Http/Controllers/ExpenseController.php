<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
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
     * Display a listing of operating expenses with filtering, summary, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::query()->with([
            'invoice:id,invoice_number,customer_id,status,total',
            'customer:id,name,phone,company',
            'creator:id,name',
        ]);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $day = 'COALESCE(DATE(expenses.expense_date), DATE(expenses.created_at))';
        if ($request->filled('date_from')) {
            $query->whereRaw("{$day} >= ?", [$request->date_from]);
        }
        if ($request->filled('date_to')) {
            $query->whereRaw("{$day} <= ?", [$request->date_to]);
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function ($q) use ($term) {
                $q->where('expense_number', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%")
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhere('company', 'like', "%{$term}%");
                    })
                    ->orWhereHas('invoice', function ($iq) use ($term) {
                        $iq->where('invoice_number', 'like', "%{$term}%");
                    });
            });
        }

        $today = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('today'))
            ? (string) $request->input('today')
            : now()->toDateString();

        $summary = $request->boolean('with_summary', true)
            ? $this->listSummary(clone $query, $today)
            : null;

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if ($request->input('sort') === 'amount') {
            $query->orderBy('amount', $direction);
        } elseif ($request->input('sort') === 'expense_number') {
            $query->orderBy('expense_number', $direction);
        } else {
            $query->orderByRaw("{$day} {$direction}");
        }
        $query->orderBy('id', $direction);

        if ($request->boolean('all') || $request->input('per_page') === 'all') {
            $expenses = $query->get();
            return response()->json([
                'success' => true,
                'data' => [
                    'expenses' => $expenses,
                    'summary' => $summary,
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $expenses->count(),
                        'total' => $expenses->count(),
                        'has_more_pages' => false,
                    ],
                ],
            ]);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'expenses' => $paginated->items(),
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'has_more_pages' => $paginated->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Compute financial and operational summary for expenses matching the filters.
     */
    private function listSummary($query, string $today): array
    {
        $rows = (clone $query)->select([
            'amount',
            'category',
            'status',
            'expense_date',
            'created_at',
        ])->get();

        $total = 0.0;
        $count = 0;
        $todayTotal = 0.0;
        $todayCount = 0;
        $thisMonthTotal = 0.0;
        $thisMonthCount = 0;
        $monthPrefix = substr($today, 0, 7);

        $byCategory = [
            'shipping' => ['total' => 0.0, 'count' => 0, 'share' => 0.0],
            'packaging' => ['total' => 0.0, 'count' => 0, 'share' => 0.0],
            'handling' => ['total' => 0.0, 'count' => 0, 'share' => 0.0],
            'other' => ['total' => 0.0, 'count' => 0, 'share' => 0.0],
        ];

        $byStatus = [
            'paid' => ['total' => 0.0, 'count' => 0],
            'pending' => ['total' => 0.0, 'count' => 0],
            'approved' => ['total' => 0.0, 'count' => 0],
            'rejected' => ['total' => 0.0, 'count' => 0],
        ];

        foreach ($rows as $row) {
            $amount = (float) $row->amount;
            $date = $row->expense_date ? $row->expense_date->toDateString() : substr((string) $row->created_at, 0, 10);
            $cat = in_array($row->category, ['shipping', 'packaging', 'handling', 'other'], true) ? $row->category : 'other';
            $st = in_array($row->status, ['paid', 'pending', 'approved', 'rejected'], true) ? $row->status : 'pending';

            $total += $amount;
            $count++;

            if ($date === $today) {
                $todayTotal += $amount;
                $todayCount++;
            }

            if (str_starts_with($date, $monthPrefix)) {
                $thisMonthTotal += $amount;
                $thisMonthCount++;
            }

            $byCategory[$cat]['total'] += $amount;
            $byCategory[$cat]['count']++;

            $byStatus[$st]['total'] += $amount;
            $byStatus[$st]['count']++;
        }

        if ($total > 0) {
            foreach ($byCategory as $catKey => $val) {
                $byCategory[$catKey]['share'] = round($val['total'] / $total, 4);
            }
        }

        return [
            'total' => round($total, 2),
            'count' => $count,
            'today' => round($todayTotal, 2),
            'today_count' => $todayCount,
            'this_month' => round($thisMonthTotal, 2),
            'this_month_count' => $thisMonthCount,
            'by_category' => $byCategory,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'nullable|string|in:shipping,packaging,handling,other',
            'expense_date' => 'required|date',
            'status' => 'nullable|string|in:pending,approved,paid,rejected',
            'notes' => 'nullable|string',
            'invoice_id' => 'nullable|exists:invoices,id',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $customerId = $validated['customer_id'] ?? null;
        if (! $customerId && ! empty($validated['invoice_id'])) {
            $customerId = Invoice::where('id', $validated['invoice_id'])->value('customer_id');
        }

        try {
            $expense = DB::transaction(function () use ($validated, $customerId) {
                $expense = Expense::create([
                    'expense_number' => 'EXP-'.str_pad((string) (((int) Expense::max('id')) + 1), 6, '0', STR_PAD_LEFT),
                    'description' => $validated['description'],
                    'amount' => $validated['amount'],
                    'category' => $validated['category'] ?? 'other',
                    'expense_date' => $validated['expense_date'],
                    'notes' => $validated['notes'] ?? null,
                    'invoice_id' => $validated['invoice_id'] ?? null,
                    'customer_id' => $customerId,
                    'status' => $validated['status'] ?? Expense::STATUS_PENDING,
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

        return response()->json(['data' => $expense->load(['invoice', 'customer', 'creator'])], 201);
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
            'amount' => 'sometimes|numeric|min:0.01',
            'category' => 'sometimes|string|in:shipping,packaging,handling,other',
            'expense_date' => 'sometimes|date',
            'notes' => 'nullable|string',
            'status' => 'sometimes|string|in:pending,approved,rejected,paid',
            'invoice_id' => 'nullable|exists:invoices,id',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        if (array_key_exists('invoice_id', $validated) && empty($validated['customer_id'])) {
            if ($validated['invoice_id']) {
                $validated['customer_id'] = Invoice::where('id', $validated['invoice_id'])->value('customer_id');
            } else {
                $validated['customer_id'] = null;
            }
        }

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

        return response()->json(['data' => $expense->refresh()->load(['invoice', 'customer', 'creator'])]);
    }

    /**
     * Remove the specified resource from storage.
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

        return response()->json([
            'success' => true,
            'message' => 'تم عكس وحذف المصروف بنجاح',
        ]);
    }
}

