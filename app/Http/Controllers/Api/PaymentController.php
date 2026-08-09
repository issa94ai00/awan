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

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'invoice_id' => 'nullable|exists:invoices,id',
            'customer_id' => 'required|exists:customers,id',
            'payment_method' => 'required|in:cash,card,bank_transfer,check',
            'status' => 'required|in:pending,completed,failed,refunded',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldAmount = $payment->amount;
        $payment->update($validated);

        if ($payment->invoice && $oldAmount != $payment->amount) {
            $difference = $payment->amount - $oldAmount;
            $payment->invoice->increment('paid_amount', $difference);
            $payment->invoice->decrement('due_amount', $difference);
            
            // Update invoice status based on payment completion
            if ($payment->invoice->due_amount <= 0) {
                $payment->invoice->markAsDelivered();
            }
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
