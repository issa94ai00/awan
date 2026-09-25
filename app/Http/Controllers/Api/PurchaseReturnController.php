<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\Purchasing\PurchaseReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Returning goods to a supplier.
 *
 * The counterpart of the credit note on the sales side. Until this existed the
 * only way to record a return was a stock adjustment, which books the goods out
 * as shrinkage — so a faulty delivery sent back looked exactly like stock lost,
 * and the supplier stayed owed in full for goods they had taken back.
 */
class PurchaseReturnController extends Controller
{
    public function __construct(private PurchaseReturnService $returns)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseReturn::query();

        if ($request->filled('supplier_id')) {
            $query->where('purchase_returns.supplier_id', $request->supplier_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('purchase_returns.warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('purchase_receipt_id')) {
            $query->where('purchase_returns.purchase_receipt_id', $request->purchase_receipt_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('purchase_returns.return_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('purchase_returns.return_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $term = '%'.trim((string) $request->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('purchase_returns.return_number', 'like', $term)
                    ->orWhere('purchase_returns.reason', 'like', $term)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term))
                    ->orWhereHas('purchaseReceipt', fn ($r) => $r->where('receipt_number', 'like', $term));
            });
        }

        // Totals for whatever is filtered, taken before paging.
        $summary = $request->boolean('with_summary') ? $this->summary(clone $query) : null;

        $returns = $query
            ->with(['supplier', 'warehouse', 'items.product', 'purchaseReceipt:id,receipt_number', 'creator:id,name'])
            ->latest('return_date')->latest('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return response()->json([
            'success' => true,
            'message' => 'Purchase returns retrieved successfully',
            'data' => [
                'returns' => $returns->items(),
                'pagination' => [
                    'current_page' => $returns->currentPage(),
                    'last_page' => $returns->lastPage(),
                    'per_page' => $returns->perPage(),
                    'total' => $returns->total(),
                    'has_more_pages' => $returns->hasMorePages(),
                ],
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Count, credit and tax for the filtered returns, and how far the credit
     * fell short of (or exceeded) what the goods cost us.
     *
     * @return array{count:int,credit:float,tax:float,cost:float,variance:float}
     */
    private function summary($query): array
    {
        $ids = $query->select('purchase_returns.id');

        $totals = PurchaseReturn::whereIn('id', $ids)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(credit_amount), 0) as credit, COALESCE(SUM(tax_amount), 0) as tax')
            ->first();

        $cost = (float) PurchaseReturnItem::whereIn('purchase_return_id', $ids)
            ->selectRaw('COALESCE(SUM(unit_cost * quantity), 0) as cost')
            ->value('cost');

        $credit = round((float) $totals->credit, 2);

        return [
            'count' => (int) $totals->count,
            'credit' => $credit,
            'tax' => round((float) $totals->tax, 2),
            'cost' => round($cost, 2),
            // Credit is the header figure, so an agreed lump sum is honoured.
            'variance' => round($credit - $cost, 2),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_receipt_id' => 'nullable|exists:purchase_receipts,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'return_date' => 'nullable|date',
            'reason' => 'nullable|string|max:255',
            'tax_amount' => 'nullable|numeric|min:0',
            // An agreed figure for the whole return, when the supplier credits
            // something other than what the lines add up to.
            'credit_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ], [
            'supplier_id.required' => 'يجب اختيار المورّد الذي تُرجع إليه البضاعة',
            'items.required' => 'يجب تحديد الأصناف المرتجعة',
        ]);

        try {
            $return = $this->returns->record($validated['items'], $validated);
        } catch (RuntimeException $e) {
            // Covers both refusals that matter here: stock that is not on the
            // shelf, and a period that has been closed.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل المردود، وخرجت البضاعة من المستودع ورُحّل قيدها',
            'data' => $return,
            'supplier_balance' => round((float) $return->supplier?->fresh()?->balance, 2),
        ], 201);
    }

    public function show(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $purchaseReturn->load(['supplier', 'warehouse', 'items.product', 'purchaseReceipt', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase return retrieved successfully',
            'data' => $purchaseReturn,
        ]);
    }

    /**
     * A return that has left the warehouse and reached the books is not
     * deletable: the goods are physically gone and the supplier has been
     * credited. Reversing it means receiving the goods back, which is a
     * purchase receipt, not a delete.
     */
    public function destroy(PurchaseReturn $purchaseReturn): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكن حذف مردود خرجت بضاعته ورُحّل قيده. سجّل إيصال استلام إن عادت البضاعة.',
            'data' => null,
        ], 422);
    }
}
