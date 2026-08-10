<?php

namespace App\Services\Accounting;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns business documents into general-ledger entries.
 *
 * Before this existed the ledger was a standalone manual-entry screen: no
 * invoice, payment, refund or credit note ever reached it, so the trial balance
 * and the financial statements described whatever somebody had typed by hand
 * rather than what the business had actually done.
 *
 * Three rules hold for everything posted here:
 *
 *  - **Balanced or nothing.** An entry is only written if debits equal credits.
 *    An unbalanced entry silently corrupts every downstream report, so the
 *    service refuses to persist one.
 *  - **Exactly once.** Each entry carries a `posting_key` derived from the
 *    document that caused it. Re-firing the same event is a no-op rather than a
 *    double posting.
 *  - **Never rewrite history.** Cancelling a document posts a reversing entry;
 *    the original stays in the books.
 *
 * Accounts are resolved through `posting_role` rather than hardcoded codes, so
 * renumbering the chart of accounts does not silently redirect postings.
 */
class LedgerPostingService
{
    /** Rounding tolerance when comparing the two sides of an entry. */
    private const EPSILON = 0.005;

    /** @var array<string,LedgerAccount|null> resolved within a single request */
    private array $accountCache = [];

    /* ------------------------------------------------------------------ *
     * Document postings
     * ------------------------------------------------------------------ */

    /**
     * Invoice issued: the customer now owes us the total, and we have earned
     * the revenue behind it. Tax collected is a liability, not income, and a
     * delivery charge billed to the customer is its own revenue stream — kept
     * apart from the carrier cost (5002) so the margin on delivery stays visible.
     *
     *   Dr  Accounts receivable   total
     *       Cr  Sales revenue           goods
     *       Cr  Shipping revenue        shipping
     *       Cr  Tax payable             tax
     */
    public function postInvoice(Invoice $invoice): ?JournalEntryHeader
    {
        $total = $this->money($invoice->total);
        if ($total <= 0) {
            return null;
        }

        $tax = $this->money($invoice->tax);
        $charges = $this->money($invoice->additional_charges ?? 0);

        // Goods revenue is whatever the total is not already accounted for, so
        // the entry balances even on legacy rows whose subtotal is out of step.
        $goods = round($total - $tax - $charges, 2);

        $label = 'فاتورة ' . $invoice->invoice_number;

        $lines = [
            ['role' => 'accounts_receivable', 'debit' => $total, 'description' => 'ذمم مدينة - ' . $label],
        ];

        if ($goods > 0) {
            $lines[] = ['role' => 'sales_revenue', 'credit' => $goods, 'description' => 'إيراد مبيعات - ' . $label];
        }

        if ($charges > 0) {
            $lines[] = ['role' => 'additional_charges_revenue', 'credit' => $charges, 'description' => 'إيراد شحن وخدمات - ' . $label];
        }

        if ($tax > 0) {
            $lines[] = ['role' => 'tax_payable', 'credit' => $tax, 'description' => 'ضريبة مستحقة - ' . $label];
        }

        return $this->post(
            key: 'invoice:' . $invoice->id,
            date: $invoice->created_at?->toDateString() ?? now()->toDateString(),
            description: 'إثبات فاتورة مبيعات ' . $invoice->invoice_number,
            lines: $lines,
            reference: $invoice,
            module: 'sales',
            currency: $invoice->currency,
        );
    }

    /**
     * Payment against a customer account.
     *
     * A refund is stored as a negative payment, so the same method handles both
     * directions: money in settles receivables, money out re-opens them.
     *
     *   received:  Dr Cash/Bank   Cr Accounts receivable
     *   refunded:  Dr Accounts receivable   Cr Cash/Bank
     */
    public function postPayment(Payment $payment): ?JournalEntryHeader
    {
        $amount = $this->money($payment->amount);
        if (abs($amount) < self::EPSILON) {
            return null;
        }

        $cashRole = $payment->payment_method === Payment::METHOD_CASH ? 'cash' : 'bank';
        $isRefund = $amount < 0;
        $magnitude = abs($amount);
        $label = ($payment->payment_number ?: $payment->reference) ?: ('#' . $payment->id);

        $lines = $isRefund
            ? [
                ['role' => 'accounts_receivable', 'debit' => $magnitude, 'description' => 'عكس ذمم - استرداد ' . $label],
                ['role' => $cashRole, 'credit' => $magnitude, 'description' => 'استرداد نقدي - ' . $label],
            ]
            : [
                ['role' => $cashRole, 'debit' => $magnitude, 'description' => 'تحصيل - ' . $label],
                ['role' => 'accounts_receivable', 'credit' => $magnitude, 'description' => 'سداد ذمم - ' . $label],
            ];

        return $this->post(
            key: 'payment:' . $payment->id,
            date: $payment->payment_date ? (string) $payment->payment_date : now()->toDateString(),
            description: ($isRefund ? 'استرداد دفعة ' : 'إثبات دفعة ') . $label,
            lines: $lines,
            reference: $payment,
            module: 'sales',
            currency: $payment->currency,
        );
    }

    /**
     * Cost of the goods that left the warehouse against a sale.
     *
     * Invoicing alone only ever recorded the revenue side, so gross profit read
     * as the full sale price and the inventory asset never came down as stock
     * shipped. This is the matching half: it is posted at the same moment the
     * stock movement is written, so the ledger and the warehouse agree on when
     * the goods stopped being ours.
     *
     *   Dr  Cost of goods sold   cost
     *       Cr  Inventory              cost
     *
     * @param  string  $key  posting key of the shipping event, e.g. "so_cogs:12"
     */
    public function postCostOfGoodsSold(
        string $key,
        float $cost,
        string $label,
        $reference = null,
        ?string $date = null,
        ?string $currency = null,
    ): ?JournalEntryHeader {
        $cost = $this->money($cost);

        // Products with no cost price yield a zero entry. Writing it would add
        // noise to the ledger without changing a single balance.
        if ($cost <= 0) {
            return null;
        }

        return $this->post(
            key: $key,
            date: $date ?? now()->toDateString(),
            description: 'تكلفة البضاعة المباعة - ' . $label,
            lines: [
                ['role' => 'cogs', 'debit' => $cost, 'description' => 'تكلفة مبيعات - ' . $label],
                ['role' => 'inventory', 'credit' => $cost, 'description' => 'إخراج مخزون - ' . $label],
            ],
            reference: $reference,
            module: 'sales',
            currency: $currency,
        );
    }

    /**
     * Cost of goods sold, split by the warehouse each unit actually came from.
     *
     * A sale filled from two places — part of it the seller's own stock, the
     * rest the main warehouse — must credit both holdings, not one pooled
     * figure. Otherwise the books record what the sale cost while losing where
     * the goods came from, and a branch's stock value can never be told apart
     * from the company's.
     *
     *   Dr  Cost of goods sold          total
     *       Cr  Inventory — warehouse A       its share
     *       Cr  Inventory — warehouse B       its share
     *
     * @param  array<int,float>  $costByWarehouse  warehouse id => cost
     */
    public function postCostOfGoodsSoldBySource(
        string $key,
        array $costByWarehouse,
        string $label,
        $reference = null,
        ?string $date = null,
        ?string $currency = null,
    ): ?JournalEntryHeader {
        $lines = [];
        $total = 0.0;

        foreach ($costByWarehouse as $warehouseId => $cost) {
            $cost = $this->money($cost);
            if ($cost <= 0) {
                continue;
            }

            $total += $cost;
            $warehouse = Warehouse::find($warehouseId);
            $name = $warehouse?->name ?? ('#' . $warehouseId);

            $lines[] = [
                'account_id' => $this->inventoryAccountIdFor((int) $warehouseId),
                'credit' => $cost,
                'description' => 'إخراج مخزون (' . $name . ') - ' . $label,
            ];
        }

        // Products with no cost price yield a zero entry; writing it would add
        // noise without moving a single balance.
        if ($lines === [] || $total <= 0) {
            return null;
        }

        array_unshift($lines, [
            'role' => 'cogs',
            'debit' => $this->money($total),
            'description' => 'تكلفة مبيعات - ' . $label,
        ]);

        return $this->post(
            key: $key,
            date: $date ?? now()->toDateString(),
            description: 'تكلفة البضاعة المباعة - ' . $label,
            lines: $lines,
            reference: $reference,
            module: 'sales',
            currency: $currency,
        );
    }

    /**
     * Goods leaving a warehouse on their way to another of ours.
     *
     *   Dr  Goods in transit      cost
     *       Cr  Inventory — source      cost
     *
     * Nothing is sold here, so no cost of sale arises: the value simply stops
     * belonging to the source and waits somewhere visible until it arrives.
     */
    public function postTransferShipment(
        string $key,
        int $fromWarehouseId,
        float $cost,
        string $label,
        $reference = null,
        ?string $date = null,
    ): ?JournalEntryHeader {
        $cost = $this->money($cost);
        if ($cost <= 0) {
            return null;
        }

        $name = Warehouse::find($fromWarehouseId)?->name ?? ('#' . $fromWarehouseId);

        return $this->post(
            key: $key,
            date: $date ?? now()->toDateString(),
            description: 'شحن مناقلة - ' . $label,
            lines: [
                ['role' => 'inventory_in_transit', 'debit' => $cost, 'description' => 'بضاعة في الطريق - ' . $label],
                ['account_id' => $this->inventoryAccountIdFor($fromWarehouseId), 'credit' => $cost,
                 'description' => 'إخراج من ' . $name . ' - ' . $label],
            ],
            reference: $reference,
            module: 'inventory',
        );
    }

    /**
     * Goods arriving at the destination warehouse.
     *
     *   Dr  Inventory — destination   cost
     *       Cr  Goods in transit            cost
     *
     * Posted for what actually arrived. A short delivery leaves the difference
     * sitting in transit rather than quietly appearing at the destination.
     */
    public function postTransferReceipt(
        string $key,
        int $toWarehouseId,
        float $cost,
        string $label,
        $reference = null,
        ?string $date = null,
    ): ?JournalEntryHeader {
        $cost = $this->money($cost);
        if ($cost <= 0) {
            return null;
        }

        $name = Warehouse::find($toWarehouseId)?->name ?? ('#' . $toWarehouseId);

        return $this->post(
            key: $key,
            date: $date ?? now()->toDateString(),
            description: 'استلام مناقلة - ' . $label,
            lines: [
                ['account_id' => $this->inventoryAccountIdFor($toWarehouseId), 'debit' => $cost,
                 'description' => 'إدخال إلى ' . $name . ' - ' . $label],
                ['role' => 'inventory_in_transit', 'credit' => $cost, 'description' => 'وصول بضاعة - ' . $label],
            ],
            reference: $reference,
            module: 'inventory',
        );
    }

    /**
     * The inventory account for a warehouse, falling back to the shared one.
     *
     * Installations that never split an order have no per-warehouse accounts,
     * and must keep posting to `inventory` exactly as before.
     */
    public function inventoryAccountIdFor(int $warehouseId): int
    {
        $id = LedgerAccount::where('warehouse_id', $warehouseId)
            ->where('type', 'asset')
            ->value('id');

        return (int) ($id ?? $this->accountIdForRole('inventory'));
    }

    /**
     * Goods received from a supplier.
     *
     * This was the missing half of inventory. Selling debited cost and credited
     * the inventory account, but nothing ever debited it back — receipts moved
     * stock in the warehouse and never reached the ledger — so the asset only
     * ever fell and ran negative against a shelf that was full.
     *
     * Buying on account raises what is owed rather than paying it; settlement is
     * the payables side's job, exactly as collection is for a sales invoice.
     *
     *   Dr  Inventory            cost of the goods
     *       Cr  Accounts payable       cost of the goods
     */
    public function postGoodsReceipt($receipt): ?JournalEntryHeader
    {
        $total = $this->money(
            collect($receipt->items ?? [])->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price)
        );

        if ($total <= 0) {
            return null;
        }

        $label = 'إيصال استلام ' . ($receipt->receipt_number ?? ('#' . $receipt->id));

        return $this->post(
            key: 'goods_receipt:' . $receipt->id,
            date: $receipt->receipt_date ? (string) $receipt->receipt_date->toDateString() : now()->toDateString(),
            description: 'إثبات ' . $label,
            lines: [
                ['role' => 'inventory', 'debit' => $total, 'description' => 'إدخال مخزون - ' . $label],
                ['role' => 'accounts_payable', 'credit' => $total, 'description' => 'ذمم موردين - ' . $label],
            ],
            reference: $receipt,
            module: 'purchases',
            currency: $receipt->currency ?? null,
        );
    }

    /**
     * Credit note: goods came back, so revenue is reduced through the contra
     * account and the customer's receivable drops.
     *
     *   Dr  Sales returns        total
     *       Cr  Accounts receivable    total
     */
    public function postCreditNote(CreditNote $creditNote): ?JournalEntryHeader
    {
        $total = $this->money($creditNote->total);
        if ($total <= 0) {
            return null;
        }

        return $this->post(
            key: 'credit_note:' . $creditNote->id,
            date: $creditNote->issue_date ? $creditNote->issue_date->toDateString() : now()->toDateString(),
            description: 'إشعار دائن ' . $creditNote->credit_note_number,
            lines: [
                ['role' => 'sales_returns', 'debit' => $total, 'description' => 'مردودات مبيعات - ' . $creditNote->credit_note_number],
                ['role' => 'accounts_receivable', 'credit' => $total, 'description' => 'تخفيض ذمم - ' . $creditNote->credit_note_number],
            ],
            reference: $creditNote,
            module: 'rma',
        );
    }

    /**
     * Operating expense.
     *
     * The cost is recognised when it is incurred. Whether cash has actually
     * left decides the other side: an unsettled expense is a payable, and
     * crediting cash for it would understate the bank and hide the obligation.
     *
     *   settled:   Dr Expense   Cr Cash
     *   unsettled: Dr Expense   Cr Accounts payable
     */
    public function postExpense($expense): ?JournalEntryHeader
    {
        $amount = $this->money($expense->amount ?? 0);
        if ($amount <= 0) {
            return null;
        }

        // A rejected expense was never incurred; posting it would invent a cost.
        if (in_array($expense->status ?? null, ['rejected', 'cancelled'], true)) {
            return null;
        }

        $role = match ($expense->category ?? null) {
            'shipping' => 'shipping_expense',
            'packaging' => 'packaging_expense',
            default => 'other_expense',
        };

        $settled = in_array($expense->status ?? null, ['paid', 'settled'], true);
        $creditRole = $settled ? 'cash' : 'accounts_payable';

        $label = $expense->expense_number ?? ('#' . $expense->id);

        return $this->post(
            key: 'expense:' . $expense->id,
            date: $expense->expense_date ? (string) $expense->expense_date : now()->toDateString(),
            description: 'مصروف ' . $label . ' - ' . ($expense->description ?? ''),
            lines: [
                ['role' => $role, 'debit' => $amount, 'description' => $expense->description ?: $label],
                [
                    'role' => $creditRole,
                    'credit' => $amount,
                    'description' => ($settled ? 'دفع مصروف ' : 'مصروف مستحق ') . $label,
                ],
            ],
            reference: $expense,
            module: 'expenses',
            currency: $expense->currency ?? null,
        );
    }

    /* ------------------------------------------------------------------ *
     * Reversal
     * ------------------------------------------------------------------ */

    /**
     * Posts the mirror image of an entry and marks the original reversed.
     * Used when a document is voided — the books keep both sides of the story.
     */
    public function reverseFor(string $postingKey, ?string $date = null): ?JournalEntryHeader
    {
        $original = JournalEntryHeader::with('lines.ledgerAccount')
            ->where('posting_key', $postingKey)
            ->first();

        if (!$original || $original->status === 'reversed') {
            return null;
        }

        $lines = $original->lines->map(fn (JournalEntryLine $line) => [
            'account_id' => $line->account_id,
            // Swap the sides.
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'description' => 'عكس: ' . ($line->description ?? ''),
        ])->all();

        $reversal = $this->post(
            key: $postingKey . ':reversal',
            date: $date ?? now()->toDateString(),
            description: 'قيد عكسي لـ ' . $original->entry_number,
            lines: $lines,
            reference: null,
            module: $original->source_module,
            currency: $original->currency,
            reversalOfId: $original->id,
        );

        if ($reversal) {
            $original->update(['status' => 'reversed']);
        }

        return $reversal;
    }

    /* ------------------------------------------------------------------ *
     * Core
     * ------------------------------------------------------------------ */

    /**
     * Writes one balanced entry, or returns the existing one if this event has
     * already been posted.
     *
     * @param array<int,array{role?:string,account_id?:int,debit?:float,credit?:float,description?:string}> $lines
     */
    public function post(
        string $key,
        string $date,
        string $description,
        array $lines,
        $reference = null,
        ?string $module = null,
        ?string $currency = null,
        ?int $reversalOfId = null,
    ): ?JournalEntryHeader {
        $existing = JournalEntryHeader::where('posting_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        $resolved = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $accountId = $line['account_id'] ?? $this->accountIdForRole($line['role'] ?? '');
            if (!$accountId) {
                // A missing account would produce a lopsided entry; refuse the
                // whole posting rather than write half of it.
                throw new RuntimeException("لا يوجد حساب مرتبط بالدور: " . ($line['role'] ?? '?'));
            }

            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $resolved[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (count($resolved) < 2) {
            return null;
        }

        if (abs($totalDebit - $totalCredit) > self::EPSILON) {
            throw new RuntimeException(sprintf(
                'قيد غير متوازن (%s): مدين %.2f ≠ دائن %.2f',
                $key,
                $totalDebit,
                $totalCredit
            ));
        }

        return DB::transaction(function () use ($key, $date, $description, $resolved, $reference, $module, $currency, $reversalOfId, $totalDebit, $totalCredit) {
            // Re-check under the transaction: two concurrent requests for the
            // same document would otherwise both pass the check above.
            $existing = JournalEntryHeader::where('posting_key', $key)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $header = JournalEntryHeader::create([
                'entry_number' => $this->nextEntryNumber(),
                'entry_date' => $date,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
                'posting_key' => $key,
                'source_module' => $module,
                'reversal_of_id' => $reversalOfId,
                'description' => $description,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'currency' => $currency ?: 'SAR',
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            foreach ($resolved as $line) {
                JournalEntryLine::create([
                    'journal_entry_header_id' => $header->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'],
                ]);

                $account = LedgerAccount::find($line['account_id']);
                $delta = LedgerAccount::signedDelta($account->type, $line['debit'], $line['credit']);
                $account->increment('balance', $delta);
            }

            return $header;
        });
    }

    private function accountIdForRole(string $role): ?int
    {
        if ($role === '') {
            return null;
        }

        if (!array_key_exists($role, $this->accountCache)) {
            $this->accountCache[$role] = LedgerAccount::where('posting_role', $role)->first();
        }

        return $this->accountCache[$role]?->id;
    }

    private function money($value): float
    {
        return round((float) $value, 2);
    }

    /** Mirrors JournalEntryController's numbering so both paths agree. */
    private function nextEntryNumber(): string
    {
        $last = JournalEntryHeader::withTrashed()->orderByDesc('id')->lockForUpdate()->first();

        return 'JE-' . str_pad((string) (($last->id ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }
}
