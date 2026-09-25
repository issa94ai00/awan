<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Accounting\LedgerPostingService;
use RuntimeException;

/**
 * Records money paid to a supplier: the payment document, the supplier's
 * running balance and the journal entry, together.
 *
 * Shared by the supplier-payments screen and the goods receipt that is paid
 * as it is booked in, so both number, settle and post a payment the same way.
 * The caller owns the transaction — a ledger failure has to roll back
 * whatever else the caller wrote with it.
 */
class SupplierPaymentRecorder
{
    public function __construct(private LedgerPostingService $ledger)
    {
    }

    /**
     * @param  array{payment_method:string, amount:float|int|string, purchase_receipt_id?:?int, purchase_order_id?:?int, payment_date?:?string, reference?:?string, notes?:?string, currency?:?string}  $data
     *
     * @throws RuntimeException when a linked receipt belongs to another
     *   supplier or is already paid, or the ledger cannot post the entry
     */
    public function record(Supplier $supplier, array $data): SupplierPayment
    {
        $amount = round((float) $data['amount'], 5);

        if (! empty($data['purchase_receipt_id'])) {
            $receipt = PurchaseReceipt::with('items')->findOrFail($data['purchase_receipt_id']);

            if ((int) $receipt->supplier_id !== (int) $supplier->id) {
                throw new RuntimeException('إيصال الاستلام المرتبط يخص مورداً آخر.');
            }

            // Paying a receipt past what it cost is an advance, and an advance
            // is not this receipt's to carry.
            if ($amount > $receipt->dueAmount() + 0.01) {
                throw new RuntimeException(sprintf(
                    'المبلغ أكبر من المتبقي على الإيصال (%s).',
                    number_format($receipt->dueAmount(), 2)
                ));
            }

            $data['purchase_order_id'] = $data['purchase_order_id'] ?? $receipt->purchase_order_id;
        }

        // Derived from the last id rather than a count: counting reuses a
        // number the moment any payment is deleted.
        $payment = SupplierPayment::create(array_merge($data, [
            'supplier_id' => $supplier->id,
            'amount' => $amount,
            'payment_number' => 'SPY-'.str_pad(
                (string) (((int) SupplierPayment::withTrashed()->max('id')) + 1),
                6,
                '0',
                STR_PAD_LEFT
            ),
            // Overwritten rather than defaulted: `nullable|date` lets an
            // explicit null through, and the column will not take one.
            'payment_date' => ($data['payment_date'] ?? null) ?: now()->toDateString(),
            'status' => 'completed',
            'created_by' => auth()->id(),
        ]));

        // The supplier balance is what we owe them, raised by every receipt.
        // Paying brings it down.
        $supplier->updateBalance(-(float) $payment->amount);

        $payment->setRelation('supplier', $supplier);
        try {
            $this->ledger->postSupplierPayment($payment);
        } catch (RuntimeException $e) {
            throw new RuntimeException('تعذّر ترحيل قيد السداد: '.$e->getMessage(), 0, $e);
        }

        return $payment;
    }
}
