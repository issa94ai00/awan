<?php

namespace App\Http\Controllers\Admin;

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

        if ($request->has('search') && $request->search) {
            $query->where('payment_number', 'like', '%' . $request->search . '%')
                ->orWhereHas('customer', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
        }

        $payments = $query->latest()->paginate(20);
        $customers = Customer::all();

        return view('admin.payments.index', compact('payments', 'customers'));
    }

    public function create()
    {
        $invoices = Invoice::where('status', Invoice::STATUS_PENDING)->get();
        $customers = Customer::all();
        return view('admin.payments.create', compact('invoices', 'customers'));
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

        // Update invoice if linked
        if ($payment->invoice) {
            $payment->invoice->increment('paid_amount', $payment->amount);
            $payment->invoice->decrement('due_amount', $payment->amount);
            
            if ($payment->invoice->due_amount <= 0) {
                $payment->invoice->markAsPaid();
            }
        }

        // Update customer balance
        $payment->customer->updateBalance(-$payment->amount);

        return redirect()->route('admin.payments.index')
            ->with('success', 'تم إنشاء الدفعة بنجاح');
    }

    public function show(Payment $payment)
    {
        $payment->load(['invoice', 'customer', 'creator']);
        return view('admin.payments.show', compact('payment'));
    }

    public function edit(Payment $payment)
    {
        $payment->load(['invoice', 'customer']);
        $invoices = Invoice::where('status', Invoice::STATUS_PENDING)->get();
        $customers = Customer::all();
        return view('admin.payments.edit', compact('payment', 'invoices', 'customers'));
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

        // Update invoice if linked and amount changed
        if ($payment->invoice && $oldAmount != $payment->amount) {
            $difference = $payment->amount - $oldAmount;
            $payment->invoice->increment('paid_amount', $difference);
            $payment->invoice->decrement('due_amount', $difference);
            
            if ($payment->invoice->due_amount <= 0) {
                $payment->invoice->markAsPaid();
            }
        }

        return redirect()->route('admin.payments.index')
            ->with('success', 'تم تحديث الدفعة بنجاح');
    }

    public function destroy(Payment $payment)
    {
        // Reverse invoice updates
        if ($payment->invoice) {
            $payment->invoice->decrement('paid_amount', $payment->amount);
            $payment->invoice->increment('due_amount', $payment->amount);
        }

        // Reverse customer balance
        $payment->customer->updateBalance($payment->amount);

        $payment->delete();

        return redirect()->route('admin.payments.index')
            ->with('success', 'تم حذف الدفعة بنجاح');
    }
}
