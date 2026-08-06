<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RmaItem;
use App\Models\RmaRequest;
use App\Models\SalesOrder;
use App\Models\Warehouse;

/**
 * Financial settlement for return requests.
 *
 * Splits a return into the two things it can produce — money back and
 * replacement goods — and makes sure a line is never compensated twice.
 *
 * Resolution semantics:
 *   refund   → money back (cash out or store credit)
 *   discard  → money back; the goods are written off rather than restocked
 *   exchange → replacement goods, credited against the returned line
 *   repair   → neither; the item is fixed and returned to the customer
 */
class RmaSettlementService
{
    /** Resolutions that entitle the customer to money back. */
    public const REFUNDABLE_RESOLUTIONS = [
        RmaItem::RESOLUTION_REFUND,
        RmaItem::RESOLUTION_DISCARD,
    ];

    /**
     * What the customer is owed in money.
     *
     * RmaRequest::getTotalRefundAmountAttribute() sums *every* line, including
     * exchange lines that also receive a replacement order — so completing a
     * return used to hand the customer both a free replacement and a full cash
     * refund for the same item. Only refundable resolutions count here.
     */
    public function refundableAmount(RmaRequest $rmaRequest): float
    {
        return (float) $rmaRequest->items
            ->whereIn('resolution', self::REFUNDABLE_RESOLUTIONS)
            ->sum('refund_amount');
    }

    /** Credit carried by the exchange lines, applied against the replacement order. */
    public function exchangeCredit(RmaRequest $rmaRequest): float
    {
        return (float) $rmaRequest->items
            ->where('resolution', RmaItem::RESOLUTION_EXCHANGE)
            ->sum('refund_amount');
    }

    /**
     * Records the money side of a completed return.
     *
     * Two distinct outcomes, because they are not the same transaction:
     *
     *  - store_credit: no cash moves. The customer's outstanding balance drops,
     *    i.e. they now owe us less.
     *  - cash / bank transfer / cheque: money physically leaves the business, so
     *    it is written as a negative Payment (status `refunded`) against the
     *    original invoice. The customer's balance is deliberately left alone —
     *    they paid for goods and got the money back, so their net position with
     *    us is unchanged. Adjusting the balance as well would credit them twice.
     *
     * @return array{credit_note: CreditNote|null, payment: Payment|null, applied_to_invoice: float, refunded: float, store_credit: float}
     */
    public function settleRefund(RmaRequest $rmaRequest, float $refundAmount, string $refundMethod, ?int $userId): array
    {
        $empty = [
            'credit_note' => null,
            'payment' => null,
            'applied_to_invoice' => 0.0,
            'refunded' => 0.0,
            'store_credit' => 0.0,
        ];

        if ($refundAmount <= 0 || !$rmaRequest->customer) {
            return $empty;
        }

        $invoice = $this->invoiceFor($rmaRequest);

        // 1. Issue the document. The invoice itself is never rewritten: the
        //    credit note is the record of what is owed for the returned goods.
        $creditNote = $this->issueCreditNote($rmaRequest, $invoice, $refundAmount, $userId);

        $remaining = $refundAmount;

        // 2. Settle against anything still outstanding on the invoice first.
        //    A return on an unpaid invoice should reduce the bill, not pay cash
        //    out to a customer who has not paid yet.
        $appliedToInvoice = 0.0;
        if ($invoice) {
            $due = max(0, (float) $invoice->due_amount);
            $appliedToInvoice = min($due, $remaining);

            if ($appliedToInvoice > 0) {
                // Only due_amount moves. total stays as issued and paid_amount
                // still reflects real cash received; the shortfall is covered by
                // the credit note rather than by a payment.
                $invoice->due_amount = round($due - $appliedToInvoice, 2);
                $invoice->save();
                $remaining -= $appliedToInvoice;
            }
        }

        // 3. Whatever is left is settled by the chosen method.
        $payment = null;
        $refunded = 0.0;
        $storeCredit = 0.0;

        if ($remaining > 0.009) {
            if ($refundMethod === 'store_credit') {
                // Stays on the customer account: their receivable drops.
                $rmaRequest->customer->updateBalance(-$remaining);
                $storeCredit = $remaining;
            } else {
                $payment = Payment::create([
                    'payment_number' => 'REF-' . $rmaRequest->rma_number,
                    'invoice_id' => $invoice?->id,
                    'customer_id' => $rmaRequest->customer_id,
                    'sales_order_id' => $rmaRequest->sales_order_id,
                    'payment_method' => $this->paymentMethodFor($refundMethod, $invoice),
                    // Negative amount + `refunded` marks money leaving, so it
                    // nets correctly against collections in payments reports.
                    'status' => Payment::STATUS_REFUNDED,
                    'amount' => -1 * $remaining,
                    'payment_date' => now()->toDateString(),
                    'reference' => $creditNote->credit_note_number,
                    'notes' => 'استرداد مرتجع ' . $rmaRequest->rma_number . ' بموجب إشعار دائن ' . $creditNote->credit_note_number,
                    'created_by' => $userId,
                ]);

                $refunded = $remaining;

                // Cash back on an already-paid invoice: paid_amount must drop,
                // otherwise collections stay overstated. due_amount is left
                // where it is — the credit note covers the difference, so the
                // customer is not put back into debt for goods they returned.
                if ($invoice) {
                    $invoice->paid_amount = round(max(0, (float) $invoice->paid_amount - $refunded), 2);
                    $invoice->save();
                }
            }
        }

        $creditNote->applied_to_invoice = round($appliedToInvoice, 2);
        $creditNote->refunded_amount = round($refunded, 2);
        $creditNote->store_credit_amount = round($storeCredit, 2);
        $creditNote->save();
        $creditNote->syncStatus();

        // Post both documents to the ledger: the credit note reduces revenue
        // through the returns account, and any cash refund is its own entry.
        $ledger = app(\App\Services\Accounting\LedgerPostingService::class);
        try {
            $ledger->postCreditNote($creditNote);
            if ($payment) {
                $ledger->postPayment($payment);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'credit_note' => $creditNote->fresh('items'),
            'payment' => $payment,
            'applied_to_invoice' => round($appliedToInvoice, 2),
            'refunded' => round($refunded, 2),
            'store_credit' => round($storeCredit, 2),
        ];
    }

    /**
     * Creates the credit note and mirrors the refundable return lines onto it,
     * so the document shows what was credited rather than just a total.
     */
    private function issueCreditNote(RmaRequest $rmaRequest, ?Invoice $invoice, float $amount, ?int $userId): CreditNote
    {
        $creditNote = CreditNote::create([
            'credit_note_number' => CreditNote::nextNumber(),
            'customer_id' => $rmaRequest->customer_id,
            'invoice_id' => $invoice?->id,
            'rma_request_id' => $rmaRequest->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => $amount,
            'tax' => 0,
            'total' => $amount,
            'status' => CreditNote::STATUS_ISSUED,
            'reason' => 'مرتجع ' . $rmaRequest->rma_number,
            'notes' => $rmaRequest->reason_description,
            'created_by' => $userId,
        ]);

        $refundableItems = $rmaRequest->items->whereIn('resolution', self::REFUNDABLE_RESOLUTIONS);

        foreach ($refundableItems as $item) {
            $quantity = max(1, (int) $item->quantity_requested);
            $lineTotal = (float) $item->refund_amount;

            $creditNote->items()->create([
                'rma_item_id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->product?->name_ar ?? $item->product?->name,
                'quantity' => $quantity,
                // total is recomputed from unit_price * quantity on save, so the
                // unit price is derived from the credited line value.
                'unit_price' => round($lineTotal / $quantity, 2),
            ]);
        }

        return $creditNote;
    }

    /**
     * Builds the replacement order for the exchange lines.
     *
     * Lines used to be written at unit_price 0.00, producing a zero-value order:
     * stock left the warehouse with no recorded value and the order was
     * invisible in sales reporting. Now each line carries the real selling
     * price and the credit from the returned goods is applied as a discount, so
     * the document shows what was shipped and what it was worth while the
     * customer still pays only the difference (normally nothing).
     *
     * When the returned goods are worth more than the replacement, the leftover
     * credit is handed back to the customer as store credit rather than being
     * dropped — they returned value they have not been compensated for.
     *
     * @return array{order: SalesOrder|null, unused_credit: float}
     */
    public function createReplacementOrder(RmaRequest $rmaRequest, ?int $userId): array
    {
        $exchangeItems = $rmaRequest->items->where('resolution', RmaItem::RESOLUTION_EXCHANGE);

        if ($exchangeItems->isEmpty()) {
            return ['order' => null, 'unused_credit' => 0.0];
        }

        $replacementOrder = SalesOrder::create([
            'order_number' => $this->nextReplacementOrderNumber(),
            'customer_id' => $rmaRequest->customer_id,
            'status' => SalesOrder::STATUS_PENDING,
            'order_date' => now(),
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'created_by' => $userId,
            'notes' => 'طلب بديل تلقائي لطلب الإرجاع: ' . $rmaRequest->rma_number,
            'fulfillment_warehouse_id' => $rmaRequest->salesOrder?->fulfillment_warehouse_id ?? Warehouse::first()?->id,
            'shipping_address' => $rmaRequest->salesOrder?->shipping_address,
            'billing_address' => $rmaRequest->salesOrder?->billing_address,
        ]);

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $unusedCredit = 0.0;

        foreach ($exchangeItems as $item) {
            $quantity = max(1, (int) $item->quantity_requested);
            $unitPrice = $this->replacementUnitPrice($item);
            $gross = $unitPrice * $quantity;

            // The returned goods pay for the replacement, up to their value.
            $lineCredit = (float) $item->refund_amount;
            $credit = min($gross, $lineCredit);
            $unusedCredit += max(0, $lineCredit - $credit);

            $replacementOrder->items()->create([
                'product_id' => $item->exchange_product_id,
                'product_variant_id' => $item->exchange_variant_id,
                'description' => $item->exchangeProduct?->name_ar ?? $item->product?->name_ar,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $credit,
                'tax' => 0,
            ]);

            $subtotal += $gross;
            $discountTotal += $credit;
        }

        $replacementOrder->subtotal = $subtotal;
        $replacementOrder->discount = $discountTotal;
        $replacementOrder->total = max(0, $subtotal - $discountTotal);
        $replacementOrder->save();

        // Downgrading to a cheaper item leaves the customer owed the balance.
        if ($unusedCredit > 0 && $rmaRequest->customer) {
            $rmaRequest->customer->updateBalance(-$unusedCredit);
        }

        return ['order' => $replacementOrder, 'unused_credit' => round($unusedCredit, 2)];
    }

    /**
     * Price for a replacement line: the exchange product's own selling price,
     * falling back to what the customer originally paid for the returned line.
     */
    private function replacementUnitPrice(RmaItem $item): float
    {
        $exchangeProduct = $item->exchange_product_id
            ? ($item->exchangeProduct ?? Product::find($item->exchange_product_id))
            : null;

        $price = (float) ($exchangeProduct->price ?? 0);

        if ($price > 0) {
            return $price;
        }

        // Same-value swap: reuse the price from the original order line.
        $originalPrice = (float) ($item->salesOrderItem->unit_price ?? 0);
        if ($originalPrice > 0) {
            return $originalPrice;
        }

        // Last resort: derive from the credit we already calculated.
        $quantity = max(1, (int) $item->quantity_requested);
        return round((float) $item->refund_amount / $quantity, 2);
    }

    private function invoiceFor(RmaRequest $rmaRequest): ?Invoice
    {
        if (!$rmaRequest->sales_order_id) {
            return null;
        }

        return Invoice::where('sales_order_id', $rmaRequest->sales_order_id)->latest('id')->first();
    }

    /**
     * Maps the RMA refund method onto the payments table's own enum.
     * `original` means "however they paid us", so it follows the invoice.
     */
    private function paymentMethodFor(string $refundMethod, ?Invoice $invoice): string
    {
        $allowed = [Payment::METHOD_CASH, Payment::METHOD_CARD, Payment::METHOD_BANK_TRANSFER, Payment::METHOD_CHECK];

        if (in_array($refundMethod, $allowed, true)) {
            return $refundMethod;
        }

        if ($refundMethod === 'original' && $invoice && in_array($invoice->payment_method, $allowed, true)) {
            return $invoice->payment_method;
        }

        return Payment::METHOD_CASH;
    }

    private function nextReplacementOrderNumber(): string
    {
        return 'SO-RPL-' . str_pad((string) (SalesOrder::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }
}
