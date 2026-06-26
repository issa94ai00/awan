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

        $validated['payment_number'] = 'PAY-' . str_pad(Payment::count() + 1, 6, '0', STR_PAD_LEFT);
        $validated['status'] = Payment::STATUS_COMPLETED;
        $validated['created_by'] = auth()->id();

        $payment = Payment::create($validated);

        if ($payment->invoice) {
            $payment->invoice->increment('paid_amount', $payment->amount);
            $payment->invoice->decrement('due_amount', $payment->amount);
            
            // Update invoice status based on payment completion
            if ($payment->invoice->due_amount <= 0) {
                $payment->invoice->markAsDelivered();
            }
        }

        $payment->customer->updateBalance(-$payment->amount);

        $payment->load(['invoice', 'customer', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الدفعة بنجاح',
            'data' => $payment
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

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الدفعة بنجاح',
            'data' => null
        ]);
    }
}
