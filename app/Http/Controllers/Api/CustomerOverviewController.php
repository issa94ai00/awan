<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * The 360° view behind the customer profile screen.
 *
 * Everything the page needs arrives in one response instead of the browser
 * firing a request per tab. That matters for more than latency: the totals are
 * computed over the customer's whole history in SQL, whereas summing whatever
 * the first page of each list happened to return would understate every figure
 * as soon as a customer had more than a page of activity.
 */
class CustomerOverviewController extends Controller
{
    /** How many recent records each tab shows. */
    private const RECENT_LIMIT = 25;

    public function show(Request $request, $id)
    {
        $customer = Customer::with('employee')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'metrics' => $this->metrics($customer),
                'sales_orders' => $this->salesOrders($customer),
                'invoices' => $this->invoices($customer),
                'payments' => $this->payments($customer),
                'credit_notes' => $this->creditNotes($customer),
                'rma_requests' => $this->rmaRequests($customer),
                'quotes' => $this->quotes($customer),
                'tickets' => $this->tickets($customer),
            ],
        ]);
    }

    /**
     * Headline figures, all aggregated in SQL over the full history.
     */
    private function metrics(Customer $customer): array
    {
        $orders = $customer->salesOrders();
        $invoices = $customer->invoices();

        // Cancelled paper should not count towards what the customer is worth.
        $liveOrders = (clone $orders)->where('status', '!=', 'cancelled');
        $liveInvoices = (clone $invoices)->where('status', '!=', 'cancelled');

        $invoicedTotal = (float) (clone $liveInvoices)->sum('total');
        $collected = (float) (clone $liveInvoices)->sum('paid_amount');

        // Refunds are stored as negative payments, so summing gives the net.
        $netPayments = (float) $customer->payments()->sum('amount');
        $refunded = abs((float) $customer->payments()->where('amount', '<', 0)->sum('amount'));

        $openCredit = $customer->creditNotes()
            ->where('status', '!=', CreditNote::STATUS_CANCELLED)
            ->get()
            ->sum(fn (CreditNote $note) => $note->open_amount);

        return [
            'balance' => round((float) $customer->balance, 2),
            'credit_limit' => round((float) $customer->credit_limit, 2),
            'remaining_credit' => round((float) $customer->remaining_credit, 2),
            // Flags a customer who has spent past what they were approved for.
            'over_credit_limit' => (float) $customer->credit_limit > 0
                && (float) $customer->balance > (float) $customer->credit_limit,

            'orders_count' => (clone $orders)->count(),
            'orders_total' => round((float) (clone $liveOrders)->sum('total'), 2),
            'open_orders_count' => (clone $orders)
                ->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped'])
                ->count(),

            'invoices_count' => (clone $invoices)->count(),
            'invoiced_total' => round($invoicedTotal, 2),
            'collected_total' => round($collected, 2),
            'outstanding_total' => round(max(0, $invoicedTotal - $collected), 2),

            'payments_count' => $customer->payments()->count(),
            'net_payments' => round($netPayments, 2),
            'refunded_total' => round($refunded, 2),

            'credit_notes_count' => $customer->creditNotes()->count(),
            'open_credit_total' => round((float) $openCredit, 2),

            'returns_count' => $customer->rmaRequests()->count(),
            'open_returns_count' => $customer->rmaRequests()
                ->whereIn('status', ['pending', 'approved', 'received'])
                ->count(),

            'quotes_count' => $customer->quotes()->count(),
            'open_tickets_count' => $customer->tickets()
                ->whereNotIn('status', ['closed', 'resolved'])
                ->count(),

            'first_order_at' => (clone $orders)->min('order_date'),
            'last_order_at' => (clone $orders)->max('order_date'),
        ];
    }

    private function salesOrders(Customer $customer)
    {
        return $customer->salesOrders()
            ->latest('order_date')->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'order_number', 'status', 'order_date', 'total']);
    }

    private function invoices(Customer $customer)
    {
        return $customer->invoices()
            ->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'invoice_number', 'status', 'total', 'paid_amount', 'due_amount', 'created_at', 'due_date']);
    }

    private function payments(Customer $customer)
    {
        return $customer->payments()
            ->with('invoice:id,invoice_number')
            ->latest('payment_date')->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'payment_number', 'invoice_id', 'payment_method', 'status', 'amount', 'payment_date', 'reference']);
    }

    private function creditNotes(Customer $customer)
    {
        return $customer->creditNotes()
            ->with(['invoice:id,invoice_number', 'rmaRequest:id,rma_number'])
            ->latest('issue_date')->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    private function rmaRequests(Customer $customer)
    {
        return $customer->rmaRequests()
            ->with('salesOrder:id,order_number')
            ->latest('requested_at')->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'rma_number', 'sales_order_id', 'status', 'type', 'reason', 'refund_amount', 'requested_at']);
    }

    private function quotes(Customer $customer)
    {
        return $customer->quotes()
            ->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'quote_number', 'status', 'total', 'valid_until', 'created_at']);
    }

    private function tickets(Customer $customer)
    {
        return $customer->tickets()
            ->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'subject', 'status', 'priority', 'created_at']);
    }
}
