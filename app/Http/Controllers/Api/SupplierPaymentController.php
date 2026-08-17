<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Paying suppliers — the settlement side of purchasing.
 *
 * Receiving goods raises what is owed (`postGoodsReceipt`); this is what
 * brings it back down. Until it existed the payable only ever grew, so the
 * supplier's balance and the payables account both described a debt that had
 * in fact been paid.
 *
 * Three records move together on every payment, inside one transaction: the
 * payment document, the supplier's running balance, and the journal entry. A
 * ledger failure rolls the other two back rather than leaving the supplier
 * settled in the operational records and still owed in the books.
 */
class SupplierPaymentController extends Controller
{
    public function __construct(private LedgerPostingService $ledger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = SupplierPayment::with(['supplier', 'purchaseReceipt', 'creator']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        // Summed before paginating: paginate() puts a limit on the builder, so
        // a sum taken afterwards would only cover the page being shown.
        $totalPaid = round((float) (clone $query)->sum('amount'), 2);

        $payments = $query->latest('payment_date')->latest('id')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Supplier payments retrieved successfully',
            'data' => [
                'payments' => $payments->items(),
                // What the filtered period cost in total, so the screen does
                // not add up one page of rows and call it the answer.
                'total_paid' => $totalPaid,
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'has_more_pages' => $payments->hasMorePages(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_receipt_id' => 'nullable|exists:purchase_receipts,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'payment_method' => 'required|in:cash,bank_transfer,check,card',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'currency' => 'nullable|string|max:10',
        ], [
            'supplier_id.required' => 'يجب اختيار المورّد',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
        ]);

        $supplier = Supplier::findOrFail($validated['supplier_id']);

        try {
            $payment = DB::transaction(function () use ($validated, $supplier) {
                // Derived from the last id rather than a count: counting reuses
                // a number the moment any payment is deleted.
                $payment = SupplierPayment::create(array_merge($validated, [
                    'payment_number' => 'SPY-'.str_pad(
                        (string) (((int) SupplierPayment::withTrashed()->max('id')) + 1),
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
                    // Overwritten rather than defaulted: `nullable|date` lets an
                    // explicit null through, and the column will not take one.
                    'payment_date' => ($validated['payment_date'] ?? null) ?: now()->toDateString(),
                    'status' => 'completed',
                    'created_by' => auth()->id(),
                ]));

                // The supplier balance is what we owe them, raised by every
                // receipt. Paying brings it down.
                $supplier->updateBalance(-(float) $payment->amount);

                $payment->setRelation('supplier', $supplier);
                $this->ledger->postSupplierPayment($payment);

                return $payment;
            });
        } catch (\RuntimeException $e) {
            // A chart of accounts missing `accounts_payable`, `cash` or `bank`
            // cannot record this, and a payment that is not in the books is
            // worse than one that was refused.
            return response()->json([
                'success' => false,
                'message' => 'تعذّر ترحيل قيد السداد: '.$e->getMessage(),
                'data' => null,
            ], 422);
        }

        $payment->load(['supplier', 'purchaseReceipt', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل السداد للمورّد وترحيل قيده المحاسبي',
            'data' => $payment,
            'supplier_balance' => round((float) $supplier->fresh()->balance, 2),
        ], 201);
    }

    public function show(SupplierPayment $supplierPayment): JsonResponse
    {
        $supplierPayment->load(['supplier', 'purchaseReceipt', 'purchaseOrder', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Supplier payment retrieved successfully',
            'data' => $supplierPayment,
        ]);
    }

    /**
     * Cancels a payment.
     *
     * The books keep both halves: the original entry stays and a mirror entry
     * cancels it, the same way a voided customer payment is handled. Deleting
     * the entry instead would leave the trial balance describing a period that
     * no document explains.
     *
     * There is deliberately no update. Correcting the amount of a settled
     * payment means cancelling it and recording the right one — which leaves a
     * trail — rather than rewriting a document the ledger has already seen.
     */
    public function destroy(SupplierPayment $supplierPayment): JsonResponse
    {
        DB::transaction(function () use ($supplierPayment) {
            $this->ledger->reverseFor($supplierPayment->postingKey());

            // What was paid is owed again.
            $supplierPayment->supplier?->updateBalance((float) $supplierPayment->amount);

            $supplierPayment->update(['status' => 'cancelled']);
            $supplierPayment->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء السداد وترحيل قيد عكسي له',
            'data' => null,
        ]);
    }

    /**
     * What is still owed to each supplier, and what has been paid so far.
     *
     * Reads the supplier balances that receipts and payments maintain, so the
     * purchasing screen can show the outstanding position without adding up
     * every document in the browser.
     */
    public function outstanding(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderByDesc('balance')
            ->get(['id', 'name', 'balance', 'currency'])
            ->map(fn ($supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'currency' => $supplier->currency,
                'balance' => round((float) $supplier->balance, 2),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Supplier balances retrieved successfully',
            'data' => [
                'suppliers' => $suppliers->values(),
                'total_outstanding' => round($suppliers->sum('balance'), 2),
            ],
        ]);
    }
}
