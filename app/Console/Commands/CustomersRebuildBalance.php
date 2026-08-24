<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds every customer's cached balance from its source documents.
 *
 * `customers.balance` is maintained by increments — invoice creation,
 * payments, RMA settlements and admin adjustments each nudge it independently
 * from over a dozen call sites. Nothing here is wrong on its own, but nothing
 * checks the fifteen of them agree with each other either, and the same class
 * of drift that hit `products.stock_quantity` (see `inventory:check`) applies
 * here for the same reason: a cache kept in step by hand eventually isn't.
 *
 * The receivable a customer's invoices carry — total minus whatever has been
 * paid or credited against it — is already tracked per document as
 * `invoices.due_amount`, and `PaymentRecorder` keeps it moving in lockstep
 * with the balance on every normal collection. Cancelling an invoice
 * deliberately leaves its due_amount alone (see InvoiceController@updateStatus):
 * a paid, later-cancelled invoice is meant to leave the customer in credit,
 * not zero out. So the rebuild sums due_amount across every invoice
 * regardless of status — that is what the balance has actually been tracking
 * all along.
 *
 * One further adjustment isn't visible on any invoice: an RMA settled as
 * store credit beyond what the original invoice owed (RmaSettlementService)
 * reduces the balance with nothing to show for it on the invoice itself — but
 * it *is* recorded on the credit note as `store_credit_amount`, so that comes
 * off the total too.
 *
 * Known gap: a replacement order created for more than the returned item's
 * value (`RmaSettlementService::createReplacementOrder`) can leave unused
 * credit that adjusts the balance directly with no credit-note or invoice
 * trail at all. There is currently no data exercising that path, so it does
 * not affect today's numbers, but a customer who goes through it later will
 * need that credit re-entered by hand after running this command.
 */
class CustomersRebuildBalance extends Command
{
    protected $signature = 'customers:rebuild-balance
                            {--dry-run : Report the drift and change nothing}';

    protected $description = "Recompute every customer's balance from invoice due amounts and credit notes";

    private const EPSILON = 0.005;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $dueByCustomer = DB::table('invoices')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COALESCE(SUM(due_amount), 0) AS due')
            ->pluck('due', 'customer_id');

        $storeCreditByCustomer = DB::table('credit_notes')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COALESCE(SUM(store_credit_amount), 0) AS credit')
            ->pluck('credit', 'customer_id');

        $drifted = [];
        $repaired = 0;

        foreach (Customer::orderBy('id')->get() as $customer) {
            $derived = round(
                (float) ($dueByCustomer[$customer->id] ?? 0)
                - (float) ($storeCreditByCustomer[$customer->id] ?? 0),
                2
            );

            $stored = round((float) $customer->balance, 2);

            if (abs($derived - $stored) < self::EPSILON) {
                continue;
            }

            $drifted[] = [
                $customer->id,
                $customer->name,
                number_format($stored, 2),
                number_format($derived, 2),
                number_format($derived - $stored, 2),
            ];

            if (! $dryRun) {
                $customer->update(['balance' => $derived]);
                $repaired++;
            }
        }

        if ($drifted === []) {
            $this->info('كل أرصدة العملاء مطابقة للمستندات — لا شيء لإصلاحه.');

            return self::SUCCESS;
        }

        $this->table(['العميل', 'الاسم', 'المخزَّن', 'المحسوب', 'الفرق'], $drifted);

        if ($dryRun) {
            $this->warn('معاينة فقط — لم يُعدَّل أي رصيد. أعد التشغيل بلا ‎--dry-run‎ للإصلاح.');

            return self::SUCCESS;
        }

        $this->info("أُعيد بناء {$repaired} رصيد عميل من مستنداته.");

        return self::SUCCESS;
    }
}
