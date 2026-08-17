<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The one way money is recorded against an invoice.
 *
 * Collecting a payment touches four records that must agree: the payment
 * itself, the invoice's paid and due amounts, the customer's balance, and the
 * ledger. Doing that inline in each caller meant every new collection point —
 * the payments screen, delivery in the field, a future POS — reimplemented it,
 * and any one of them could forget the ledger and leave the cash unaccounted.
 *
 * Collecting is deliberately not the same act as delivering. A driver taking
 * cash at the door and a customer paying an invoice weeks later are the same
 * event to the books, and only the books' version lives here.
 */
class PaymentRecorder
{
    public function __construct(private LedgerPostingService $ledger)
    {
    }

    /**
     * Records a collection against an invoice.
     *
     * @param  array{method?:string,date?:string,reference?:string,notes?:string,sales_order_id?:int,created_by?:int}  $options
     *
     * @throws RuntimeException when the amount is not collectable
     */
    public function record(Invoice $invoice, float $amount, array $options = []): Payment
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('مبلغ التحصيل يجب أن يكون أكبر من صفر.');
        }

        $due = $this->outstanding($invoice);

        // Overpaying turns the receivable negative — the books would then say
        // the customer is owed money they never advanced.
        if ($amount - $due > 0.009) {
            throw new RuntimeException(sprintf(
                'المبلغ (%s) يتجاوز المتبقي على الفاتورة %s (%s).',
                number_format($amount, 2),
                $invoice->invoice_number,
                number_format($due, 2)
            ));
        }

        return DB::transaction(function () use ($invoice, $amount, $options) {
            $payment = Payment::create([
                // Derived from the last id: counting reuses a number as soon as
                // any payment is deleted, and two concurrent collections collide.
                'payment_number' => 'PAY-' . str_pad((string) (((int) Payment::max('id')) + 1), 6, '0', STR_PAD_LEFT),
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'sales_order_id' => $options['sales_order_id'] ?? $invoice->sales_order_id,
                'payment_method' => $options['method'] ?? Payment::METHOD_CASH,
                'status' => Payment::STATUS_COMPLETED,
                'amount' => $amount,
                'payment_date' => $options['date'] ?? now()->toDateString(),
                'reference' => $options['reference'] ?? null,
                'notes' => $options['notes'] ?? null,
                'currency' => $invoice->currency ?: base_currency_code(),
                'created_by' => $options['created_by'] ?? auth()->id(),
            ]);

            $paid = round((float) $invoice->paid_amount + $amount, 2);

            $invoice->update([
                'paid_amount' => $paid,
                'due_amount' => max(0, round((float) $invoice->total - $paid, 2)),
                // Stamped only when nothing is left owing. The status is left
                // alone: whether the goods arrived is the order's business, and
                // moving the invoice to "delivered" because it was paid is what
                // used to make paid and delivered indistinguishable.
                'paid_at' => $paid + 0.009 >= (float) $invoice->total ? now() : $invoice->paid_at,
            ]);

            // The customer owes us less now.
            $invoice->customer?->updateBalance(-$amount);

            $this->ledger->postPayment($payment);

            return $payment;
        });
    }

    /** What is still owed, trusting the amounts rather than the stored column. */
    public function outstanding(Invoice $invoice): float
    {
        return max(0, round((float) $invoice->total - (float) $invoice->paid_amount, 2));
    }
}
