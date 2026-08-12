<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Customer;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['invoice', 'customer', 'creator']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        $payments = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully',
            'data' => [
                'payments' => $payments->items(),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'has_more_pages' => $payments->hasMorePages(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'nullable|exists:invoices,id',
            'customer_id' => 'required|exists:customers,id',
            'payment_method' => 'required|in:cash,card,bank_transfer,check',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // A collection against an invoice goes through PaymentRecorder, the same
        // path delivery-time settlement uses, so the invoice, the customer
        // balance and the ledger always move together. This method used to do
        // all four by hand and marked a fully paid invoice as *delivered* —
        // describing the goods as having arrived because the money had.
        if (!empty($validated['invoice_id'])) {
            $invoice = Invoice::findOrFail($validated['invoice_id']);

            try {
                $payment = app(\App\Services\Sales\PaymentRecorder::class)->record(
                    $invoice,
                    (float) $validated['amount'],
                    [
                        'method' => $validated['payment_method'],
                        'date' => $validated['payment_date'] ?? null,
                        'reference' => $validated['reference'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                    ]
                );
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
            }

            $payment->load(['invoice', 'customer', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدفعة وترحيل قيدها المحاسبي',
                'data' => $payment,
            ], 201);
        }

        // An on-account payment with no invoice behind it: still recorded and
        // still posted, but there is no invoice to settle.
        $validated['payment_number'] = 'PAY-' . str_pad((string) (((int) Payment::max('id')) + 1), 6, '0', STR_PAD_LEFT);
        $validated['status'] = Payment::STATUS_COMPLETED;
        $validated['created_by'] = auth()->id();

        $payment = Payment::create($validated);
        $payment->customer->updateBalance(-$payment->amount);

        $postingError = null;
        try {
            app(\App\Services\Accounting\LedgerPostingService::class)->postPayment($payment);
        } catch (\Throwable $e) {
            $postingError = $e->getMessage();
            report($e);
        }

        $payment->load(['invoice', 'customer', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الدفعة بنجاح',
            'data' => $payment,
            'accounting_warning' => $postingError,
        ], 201);
    }

    public function show(Payment $payment)
    {
        $payment->load(['invoice', 'customer', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved successfully',
            'data' => $payment
        ]);
    }

    /**
     * Corrects a payment already on the books.
     *
     * Reassigning a payment to a different invoice or customer is refused
     * rather than half-handled — the old code accepted both fields but never
     * moved the balance or the ledger off the original invoice, so the
     * receivable stayed charged to whoever it started on while the payment
     * record pointed elsewhere. Only what a correction actually means —
     * the amount, method, date, reference, notes — is editable here.
     *
     * An amount change reverses the original ledger entry and posts the
     * corrected one under its own key, the same way an invoice is restated
     * after it changes (`SalesOrderWorkflowService::restateInvoice`) — the
     * original key now belongs to a reversed entry, so re-posting under it
     * would just hand back that reversed header instead of writing a new one.
     *
     * This also drops the old `markAsDelivered()` call: an invoice reaching
     * zero due says the money arrived, not that the goods did, and conflating
     * the two is exactly the bug `store()` already had to be fixed for.
     */
    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,bank_transfer,check',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldAmount = round((float) $payment->amount, 2);
        $newAmount = round((float) $validated['amount'], 2);
        $invoice = $payment->invoice;

        if ($invoice && abs($newAmount - $oldAmount) > 0.009) {
            // What the invoice's other payments already cover, so the new
            // amount is checked against what is actually left rather than
            // against the total this payment used to claim.
            $otherPaid = round((float) $invoice->paid_amount - $oldAmount, 2);

            if ($newAmount - ((float) $invoice->total - $otherPaid) > 0.009) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf(
                        'المبلغ (%s) يتجاوز المتبقي على الفاتورة %s.',
                        number_format($newAmount, 2),
                        $invoice->invoice_number
                    ),
                    'data' => null,
                ], 422);
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $invoice, $validated, $oldAmount, $newAmount, $otherPaid) {
                $payment->update($validated);

                $paid = round($otherPaid + $newAmount, 2);
                $invoice->update([
                    'paid_amount' => $paid,
                    'due_amount' => max(0, round((float) $invoice->total - $paid, 2)),
                    'paid_at' => $paid + 0.009 >= (float) $invoice->total ? now() : null,
                ]);

                // The customer owes the difference more, or less, than before.
                $invoice->customer?->updateBalance($oldAmount - $newAmount);

                app(\App\Services\Accounting\LedgerPostingService::class)->reverseFor('payment:' . $payment->id);
                app(\App\Services\Accounting\LedgerPostingService::class)->postPayment(
                    $payment,
                    'payment:' . $payment->id . ':corrected:' . now()->getTimestamp()
                );
            });
        } else {
            $payment->update($validated);
        }

        $payment->load(['invoice', 'customer', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الدفعة بنجاح',
            'data' => $payment
        ]);
    }

    public function destroy(Payment $payment)
    {
        if ($payment->invoice) {
            $payment->invoice->decrement('paid_amount', $payment->amount);
            $payment->invoice->increment('due_amount', $payment->amount);
        }

        $payment->customer->updateBalance($payment->amount);

        // The books keep both sides: the original posting stays and a mirror
        // entry cancels it, rather than deleting history.
        try {
            app(\App\Services\Accounting\LedgerPostingService::class)->reverseFor('payment:' . $payment->id);
        } catch (\Throwable $e) {
            report($e);
        }

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الدفعة بنجاح',
            'data' => null
        ]);
    }
}
