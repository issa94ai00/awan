<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
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
        $query = PurchaseReturn::with(['supplier', 'warehouse', 'items.product', 'creator']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('return_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('return_date', '<=', $request->date_to);
        }

        $returns = $query->latest('return_date')->latest('id')
            ->paginate($request->input('per_page', 20));

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
            ],
        ]);
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
        $purchaseReturn->load(['supplier', 'warehouse', 'items.product', 'purchaseReceipt', 'creator']);

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
