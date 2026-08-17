<?php

namespace App\Services\Accounting;

use App\Exceptions\ClosedPeriodException;
use App\Models\AccountingPeriod;
use App\Models\CreditNote;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PurchaseReturn;
use App\Models\SupplierPayment;
use App\Models\Warehouse;
use App\Services\CurrencyService;
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

    /** The base currency, read once per request. */
    private ?string $baseCurrency = null;

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
    /**
     * @param  ?string  $key  overrides the default `payment:{id}` posting key —
     *                        used when correcting an already-posted payment,
     *                        whose original key now belongs to a reversed entry.
     */
    public function postPayment(Payment $payment, ?string $key = null): ?JournalEntryHeader
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
            key: $key ?? ('payment:' . $payment->id),
            date: $payment->payment_date ? (string) $payment->payment_date : now()->toDateString(),
            description: ($isRefund ? 'استرداد دفعة ' : 'إثبات دفعة ') . $label,
            lines: $lines,
            reference: $payment,
            module: 'sales',
            currency: $payment->currency,
        );
    }

    /**
     * Money paid out to a supplier.
     *
     * The other half of `postGoodsReceipt`. Receiving goods credited accounts
     * payable on every purchase, and nothing ever debited it back, so the
     * liability grew for the life of the installation however much had really
     * been settled — the balance sheet was wrong by the whole amount paid.
     *
     *   paid:     Dr Accounts payable   Cr Cash/Bank
     *   refunded: Dr Cash/Bank          Cr Accounts payable
     *
     * A negative amount is a refund from the supplier, and re-opens what is
     * owed — the same convention `postPayment` uses on the customer side.
     *
     * @param  ?string  $key  overrides the default posting key, for a payment
     *                        being corrected after its original entry was
     *                        reversed (that key now belongs to the reversal).
     */
    public function postSupplierPayment(SupplierPayment $payment, ?string $key = null): ?JournalEntryHeader
    {
        $amount = $this->money($payment->amount);
        if (abs($amount) < self::EPSILON) {
            return null;
        }

        $cashRole = $payment->payment_method === SupplierPayment::METHOD_CASH ? 'cash' : 'bank';
        $isRefund = $amount < 0;
        $magnitude = abs($amount);
        $label = ($payment->payment_number ?: $payment->reference) ?: ('#'.$payment->id);
        $supplier = $payment->supplier?->name;
        $suffix = ($supplier ? $supplier.' - ' : '').$label;

        $lines = $isRefund
            ? [
                ['role' => $cashRole, 'debit' => $magnitude, 'description' => 'استرداد من مورّد - '.$suffix],
                ['role' => 'accounts_payable', 'credit' => $magnitude, 'description' => 'عكس سداد مورّد - '.$suffix],
            ]
            : [
                ['role' => 'accounts_payable', 'debit' => $magnitude, 'description' => 'سداد ذمم موردين - '.$suffix],
                ['role' => $cashRole, 'credit' => $magnitude, 'description' => 'صرف نقدي - '.$suffix],
            ];

        return $this->post(
            key: $key ?? $payment->postingKey(),
            date: $payment->payment_date ? (string) $payment->payment_date->toDateString() : now()->toDateString(),
            description: ($isRefund ? 'استرداد من مورّد ' : 'سداد لمورّد ').$suffix,
            lines: $lines,
            reference: $payment,
            module: 'purchases',
            currency: $payment->currency,
        );
    }

    /**
     * Wages earned, recognised in the period they were worked.
     *
     * Payroll ran entirely outside the ledger: salaries were computed, stored
     * and paid without a single entry, so the largest recurring cost most
     * businesses carry appeared in no income statement the system produced,
     * and the cash it consumed left the books unexplained.
     *
     *   Dr  Salaries expense              gross earnings
     *       Cr  Payroll deductions payable      whatever was withheld
     *       Cr  Salaries payable                what the employee is owed
     *
     * Gross is what was earned — basic plus overtime plus bonuses — and it is
     * the whole of the cost to the business. Deductions do not reduce that
     * cost; they only change who ends up holding part of it, which is why they
     * are a liability rather than a smaller expense.
     *
     * The accrual is deliberately separate from the payment. Recognising both
     * at once would date the cost to the day the transfer cleared rather than
     * the period that earned it, and a month closed before payday would show
     * no wages at all.
     */
    public function postPayrollAccrual(Payroll $payroll): ?JournalEntryHeader
    {
        $gross = $this->money(
            (float) $payroll->basic_salary + (float) $payroll->overtime_pay + (float) $payroll->bonuses
        );
        $deductions = $this->money($payroll->deductions);
        $net = round($gross - $deductions, 2);

        if ($gross <= 0) {
            return null;
        }

        $label = $payroll->payroll_number.($payroll->employee?->name ? ' - '.$payroll->employee->name : '');

        $lines = [
            ['role' => 'salaries_expense', 'debit' => $gross, 'description' => 'رواتب وأجور - '.$label,
             'employee_id' => $payroll->employee_id],
        ];

        if ($deductions > 0) {
            // An advance being repaid is not a liability the business now
            // holds — it is an asset coming back. Everything else stays on the
            // neutral payable, because the record does not say what it is.
            $deductionRole = ($payroll->deduction_type ?? 'general') === 'advance'
                ? 'employee_advances'
                : 'payroll_deductions_payable';

            $lines[] = ['role' => $deductionRole, 'credit' => $deductions,
                        'description' => ($deductionRole === 'employee_advances' ? 'سداد سلفة - ' : 'استقطاعات - ').$label,
                        'employee_id' => $payroll->employee_id];
        }

        if ($net > 0) {
            $lines[] = ['role' => 'salaries_payable', 'credit' => $net,
                        'description' => 'صافي مستحق - '.$label, 'employee_id' => $payroll->employee_id];
        }

        return $this->post(
            key: 'payroll:'.$payroll->id,
            // The cost belongs to the period worked, so it is dated at the end
            // of that period rather than whenever the record was touched.
            date: $payroll->pay_period_end
                ? (string) $payroll->pay_period_end->toDateString()
                : now()->toDateString(),
            description: 'استحقاق رواتب '.$label,
            lines: $lines,
            reference: $payroll,
            module: 'payroll',
        );
    }

    /**
     * A month's share of what an employee will be owed when they leave.
     *
     *   Dr  End-of-service expense    the month's share
     *       Cr  End-of-service payable      what has built up so far
     *
     * The benefit is earned by working, not by leaving. Recognising it only on
     * the last day puts years of cost into one month and, until then, leaves
     * the balance sheet silent about a debt the business has already incurred —
     * so it can look solvent while owing its staff a year of wages.
     */
    public function postEndOfServiceAccrual(Employee $employee, \Carbon\Carbon $month, float $amount): ?JournalEntryHeader
    {
        $amount = $this->money($amount);

        if ($amount <= 0) {
            return null;
        }

        $label = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) ?: ('#'.$employee->id);

        return $this->post(
            key: 'eos_accrual:'.$employee->id.':'.$month->format('Y-m'),
            // Dated to the month it was earned in, so a month closed later
            // carries its own share.
            date: $month->copy()->endOfMonth()->toDateString(),
            description: 'استحقاق مكافأة نهاية خدمة '.$month->format('Y-m').' - '.$label,
            lines: [
                ['role' => 'end_of_service_expense', 'debit' => $amount,
                 'description' => 'مكافأة نهاية خدمة - '.$label, 'employee_id' => $employee->id],
                ['role' => 'end_of_service_payable', 'credit' => $amount,
                 'description' => 'مستحق نهاية خدمة - '.$label, 'employee_id' => $employee->id],
            ],
            reference: $employee,
            module: 'payroll',
        );
    }

    /**
     * Paying the benefit out when somebody leaves.
     *
     *   Dr  End-of-service payable   what had built up
     *       Cr  Cash/Bank                  what was handed over
     *
     * Settles the liability the monthly accruals raised; the cost was
     * recognised in the years that earned it and is not charged again here.
     */
    public function postEndOfServiceSettlement(
        Employee $employee,
        float $amount,
        string $settlement = 'cash',
        ?string $date = null,
    ): ?JournalEntryHeader {
        $amount = $this->money($amount);

        if ($amount <= 0) {
            return null;
        }

        $label = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) ?: ('#'.$employee->id);

        return $this->post(
            key: 'eos_settlement:'.$employee->id,
            date: $date ?? now()->toDateString(),
            description: 'صرف مكافأة نهاية الخدمة - '.$label,
            lines: [
                ['role' => 'end_of_service_payable', 'debit' => $amount,
                 'description' => 'سداد مستحق نهاية خدمة - '.$label, 'employee_id' => $employee->id],
                ['role' => $settlement === 'bank' ? 'bank' : 'cash', 'credit' => $amount,
                 'description' => 'صرف مكافأة - '.$label, 'employee_id' => $employee->id],
            ],
            reference: $employee,
            module: 'payroll',
        );
    }

    /**
     * The wage actually leaving the business.
     *
     *   Dr  Salaries payable   net
     *       Cr  Cash/Bank            net
     *
     * Settles the liability the accrual raised; the expense was already
     * recognised there and is not touched again.
     */
    public function postPayrollPayment(Payroll $payroll): ?JournalEntryHeader
    {
        $net = $this->money($payroll->net_salary);
        if ($net <= 0) {
            return null;
        }

        $cashRole = ($payroll->payment_method ?? 'cash') === 'cash' ? 'cash' : 'bank';
        $label = $payroll->payroll_number.($payroll->employee?->name ? ' - '.$payroll->employee->name : '');

        return $this->post(
            key: 'payroll_paid:'.$payroll->id,
            date: $payroll->payment_date
                ? (string) $payroll->payment_date->toDateString()
                : now()->toDateString(),
            description: 'صرف رواتب '.$label,
            lines: [
                ['role' => 'salaries_payable', 'debit' => $net, 'description' => 'سداد مستحق - '.$label,
                 'employee_id' => $payroll->employee_id],
                ['role' => $cashRole, 'credit' => $net, 'description' => 'صرف راتب - '.$label,
                 'employee_id' => $payroll->employee_id],
            ],
            reference: $payroll,
            module: 'payroll',
        );
    }

    /**
     * A stock count that disagreed with the books.
     *
     * Adjustments moved the warehouse and never reached the ledger, so every
     * count, write-off and damaged unit widened the gap between the inventory
     * asset and the stock it is supposed to describe — silently, and in one
     * direction only for whoever was losing goods.
     *
     *   shortage: Dr Inventory shrinkage      Cr Inventory — warehouse
     *   surplus:  Dr Inventory — warehouse    Cr Inventory shrinkage
     *
     * Both directions land on the same account, so the period's net result of
     * counting is one figure on the income statement rather than a gain buried
     * in one place and a loss in another.
     *
     * @param  float  $cost  what the adjusted units were worth, always positive
     * @param  bool   $isShortage  true when stock was lost, false when found
     */
    public function postInventoryAdjustment(
        string $key,
        int $warehouseId,
        float $cost,
        bool $isShortage,
        string $label,
        $reference = null,
        ?string $date = null,
    ): ?JournalEntryHeader {
        $cost = $this->money(abs($cost));

        // Units with no cost behind them — an item never priced, or a count on
        // stock that arrived free — would write a zero entry that moves no
        // balance and only adds noise to the journal.
        if ($cost <= 0) {
            return null;
        }

        $name = Warehouse::find($warehouseId)?->name ?? ('#'.$warehouseId);
        $inventoryLine = ['account_id' => $this->inventoryAccountIdFor($warehouseId)];

        $lines = $isShortage
            ? [
                ['role' => 'inventory_adjustment', 'debit' => $cost, 'description' => 'عجز جرد ('.$name.') - '.$label],
                $inventoryLine + ['credit' => $cost, 'description' => 'تخفيض مخزون ('.$name.') - '.$label],
            ]
            : [
                $inventoryLine + ['debit' => $cost, 'description' => 'زيادة مخزون ('.$name.') - '.$label],
                ['role' => 'inventory_adjustment', 'credit' => $cost, 'description' => 'زيادة جرد ('.$name.') - '.$label],
            ];

        return $this->post(
            key: $key,
            date: $date ?? now()->toDateString(),
            description: ($isShortage ? 'تسوية عجز مخزون - ' : 'تسوية زيادة مخزون - ').$label,
            lines: $lines,
            reference: $reference,
            module: 'inventory',
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
     * The inventory account for a warehouse, opening one if it has none.
     *
     * The accounts were created by a migration that walked the warehouses
     * existing at the time. Anything opened since — a new branch, a warehouse
     * added by an import — had no account of its own and quietly posted to the
     * shared one, so its stock was on the shelves but nowhere in the books, and
     * the pooled balance mixed it in with everyone else's. Provisioning here
     * means the account exists by the time the first posting needs it, however
     * the warehouse came to be.
     *
     * Falls back to the shared `inventory` account when there is no such
     * warehouse, or when the chart has no 1005 parent to hang one from — an
     * installation that never split an order keeps posting exactly as before.
     */
    public function inventoryAccountIdFor(int $warehouseId): int
    {
        $id = LedgerAccount::where('warehouse_id', $warehouseId)
            ->where('type', 'asset')
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        $warehouse = Warehouse::find($warehouseId);
        $parentId = LedgerAccount::where('code', '1005')->value('id');

        if ($warehouse && $parentId) {
            // firstOrCreate on the code: two postings for a new warehouse
            // arriving together must not open the account twice, and the code
            // is what the chart treats as unique.
            $account = LedgerAccount::firstOrCreate(
                ['code' => '1005-' . $warehouse->id],
                [
                    'parent_id' => $parentId,
                    'name' => 'مخزون - ' . $warehouse->name,
                    'type' => 'asset',
                    'account_type' => 'asset',
                    // No posting_role: the column is unique across the chart,
                    // and these are resolved by warehouse rather than by role.
                    'posting_role' => null,
                    'warehouse_id' => $warehouse->id,
                    // The books' own currency, not a literal: an account opened
                    // at runtime must not disagree with the chart around it.
                    'currency' => $this->baseCurrencyCode(),
                    'balance' => 0,
                    'opening_balance' => 0,
                    'is_active' => true,
                    'is_system' => true,
                ]
            );

            return (int) $account->id;
        }

        return (int) $this->accountIdForRole('inventory');
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
     *   Dr  Inventory — receiving warehouse   cost of the goods
     *       Cr  Accounts payable                    cost of the goods
     *
     * The debit lands on the warehouse that actually took the goods in. It used
     * to go to the pooled `inventory` account while sales credited the
     * per-warehouse accounts, so stock only ever left a warehouse's balance and
     * never entered it: every `1005-{warehouse}` account fell without limit
     * while the parent rose, and no warehouse's holding could be read off the
     * books at all. The stock ledger has always been per warehouse — the
     * receipt names one, moves the units into it and opens its FIFO layer
     * there — and this is the general ledger agreeing with it.
     */
    public function postGoodsReceipt($receipt): ?JournalEntryHeader
    {
        $total = $this->money(
            collect($receipt->items ?? [])->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price)
        );

        if ($total <= 0) {
            return null;
        }

        // Tax paid to the supplier is not part of what the goods cost: it is a
        // claim against the tax authority. Booking it into inventory — which is
        // what happened before there was an account for it — carries the stock
        // at more than it is worth, understates every margin computed from it,
        // and hides money the business is entitled to deduct.
        $tax = $this->money($receipt->tax_amount ?? 0);

        $label = 'إيصال استلام ' . ($receipt->receipt_number ?? ('#' . $receipt->id));

        // A receipt whose warehouse was never resolved falls back to the shared
        // account, which is exactly what `inventoryAccountIdFor` does for an
        // installation that has no per-warehouse accounts.
        $warehouseId = (int) ($receipt->warehouse_id ?? 0);

        $debit = $warehouseId
            ? ['account_id' => $this->inventoryAccountIdFor($warehouseId)]
            : ['role' => 'inventory'];

        $name = $warehouseId ? (Warehouse::find($warehouseId)?->name ?? ('#' . $warehouseId)) : null;

        $lines = [
            $debit + [
                'debit' => $total,
                'description' => 'إدخال مخزون' . ($name ? ' (' . $name . ')' : '') . ' - ' . $label,
            ],
        ];

        if ($tax > 0) {
            $lines[] = ['role' => 'input_vat', 'debit' => $tax, 'description' => 'ضريبة مشتريات - ' . $label];
        }

        // What the supplier is owed is the whole document, tax included — the
        // split above only decides which of our accounts carries each part.
        $lines[] = [
            'role' => 'accounts_payable',
            'credit' => round($total + $tax, 2),
            'description' => 'ذمم موردين - ' . $label,
        ];

        return $this->post(
            key: 'goods_receipt:' . $receipt->id,
            date: $receipt->receipt_date ? (string) $receipt->receipt_date->toDateString() : now()->toDateString(),
            description: 'إثبات ' . $label,
            lines: $lines,
            reference: $receipt,
            module: 'purchases',
            currency: $receipt->currency ?? null,
        );
    }

    /**
     * Buying something to keep.
     *
     *   Dr  Fixed assets      what was paid
     *       Cr  Cash/Bank/Accounts payable
     *
     * The cost becomes an asset rather than a cost of the month it was bought
     * in. Charging it as an expense would make the month of purchase look
     * disastrous and every month after it flattering, for a thing the business
     * will use for years.
     */
    public function postAssetAcquisition(FixedAsset $asset, string $settlement = 'credit'): ?JournalEntryHeader
    {
        $cost = $this->money($asset->cost);

        if ($cost <= 0) {
            return null;
        }

        $creditRole = match ($settlement) {
            'cash' => 'cash',
            'bank', 'bank_transfer' => 'bank',
            default => 'accounts_payable',
        };

        $label = $asset->asset_number.' - '.$asset->name;

        return $this->post(
            key: $asset->acquisitionKey(),
            date: (string) $asset->acquired_on->toDateString(),
            description: 'اقتناء أصل ثابت '.$label,
            lines: [
                ['role' => 'fixed_assets', 'debit' => $cost, 'description' => 'أصل ثابت - '.$label],
                ['role' => $creditRole, 'credit' => $cost, 'description' => 'ثمن أصل - '.$label],
            ],
            reference: $asset,
            module: 'assets',
        );
    }

    /**
     * One month of an asset being used up.
     *
     *   Dr  Depreciation expense        this month's slice
     *       Cr  Accumulated depreciation      what has been used up in total
     *
     * The credit does not touch the asset account: the books keep saying both
     * what the thing cost and how much of it is gone, and an asset fully
     * depreciated but still in daily use stays visible at cost rather than
     * vanishing from the register.
     */
    public function postDepreciation(FixedAsset $asset, \Carbon\Carbon $month, float $amount): ?JournalEntryHeader
    {
        $amount = $this->money($amount);

        if ($amount <= 0) {
            return null;
        }

        $label = $asset->asset_number.' - '.$asset->name;

        return $this->post(
            key: $asset->depreciationKey($month),
            // Dated the last day of the month it belongs to, so a month closed
            // later carries its own charge rather than the day it was run.
            date: $month->copy()->endOfMonth()->toDateString(),
            description: 'إهلاك '.$month->format('Y-m').' - '.$label,
            lines: [
                ['role' => 'depreciation_expense', 'debit' => $amount, 'description' => 'مصروف إهلاك - '.$label],
                ['role' => 'accumulated_depreciation', 'credit' => $amount,
                 'description' => 'مجمع إهلاك - '.$label],
            ],
            reference: $asset,
            module: 'assets',
        );
    }

    /**
     * An asset leaving the business.
     *
     *   Dr  Cash/Bank                  what it sold for, if anything
     *   Dr  Accumulated depreciation   everything charged against it so far
     *       Cr  Fixed assets                 what it originally cost
     *   and the difference is the gain or loss on disposal.
     *
     * Both the cost and its accumulated depreciation have to come off together:
     * removing one and leaving the other would show a company owning
     * depreciation on an asset it no longer has.
     */
    public function postAssetDisposal(FixedAsset $asset, float $proceeds, string $settlement = 'cash'): ?JournalEntryHeader
    {
        $cost = $this->money($asset->cost);
        $accumulated = $this->money($asset->accumulated_depreciation);
        $proceeds = $this->money($proceeds);

        if ($cost <= 0) {
            return null;
        }

        $label = $asset->asset_number.' - '.$asset->name;
        $lines = [];

        if ($proceeds > 0) {
            $lines[] = [
                'role' => $settlement === 'bank' ? 'bank' : 'cash',
                'debit' => $proceeds,
                'description' => 'متحصلات بيع أصل - '.$label,
            ];
        }

        if ($accumulated > 0) {
            $lines[] = ['role' => 'accumulated_depreciation', 'debit' => $accumulated,
                        'description' => 'إقفال مجمع الإهلاك - '.$label];
        }

        $lines[] = ['role' => 'fixed_assets', 'credit' => $cost, 'description' => 'استبعاد أصل - '.$label];

        // What it was still carried at, against what it fetched.
        $result = round($proceeds - ($cost - $accumulated), 2);

        if (abs($result) > self::EPSILON) {
            $lines[] = $result < 0
                ? ['role' => 'asset_disposal_loss', 'debit' => abs($result),
                   'description' => 'خسارة استبعاد - '.$label]
                : ['role' => 'asset_disposal_loss', 'credit' => $result,
                   'description' => 'ربح استبعاد - '.$label];
        }

        return $this->post(
            key: $asset->disposalKey(),
            date: (string) ($asset->disposed_on?->toDateString() ?? now()->toDateString()),
            description: 'استبعاد أصل ثابت '.$label,
            lines: $lines,
            reference: $asset,
            module: 'assets',
        );
    }

    /**
     * Goods sent back to the supplier.
     *
     * The purchase side had no return document at all, so the only way to
     * record one was a stock adjustment — which books the goods out as
     * shrinkage. Returning a faulty delivery therefore looked, in the income
     * statement, exactly like losing it, and the debt to that supplier stayed
     * on the books in full.
     *
     *   Dr  Accounts payable        what the supplier credits back, tax included
     *       Cr  Inventory — warehouse     what those units actually cost
     *       Cr  Input VAT                 tax reclaimed on the returned portion
     *
     * The cost comes from the FIFO layers the units were consumed from, not
     * from what the supplier is crediting. When the two differ — a restocking
     * fee, or a price agreed after the fact — the difference is a real result
     * of the return and lands in other operating expenses rather than being
     * hidden by forcing one of the two figures to match the other.
     *
     * @param  float  $cost  what the returned units cost, from the layers
     */
    public function postPurchaseReturn(PurchaseReturn $return, float $cost): ?JournalEntryHeader
    {
        $cost = $this->money($cost);
        $credit = $this->money($return->credit_amount);
        $tax = $this->money($return->tax_amount);

        if ($cost <= 0 && $credit <= 0) {
            return null;
        }

        $label = 'مردود مشتريات '.$return->return_number;
        $warehouseId = (int) ($return->warehouse_id ?? 0);

        $lines = [
            ['role' => 'accounts_payable', 'debit' => round($credit + $tax, 2),
             'description' => 'تخفيض ذمم مورّد - '.$label],
        ];

        if ($cost > 0) {
            $lines[] = ($warehouseId
                ? ['account_id' => $this->inventoryAccountIdFor($warehouseId)]
                : ['role' => 'inventory'])
                + ['credit' => $cost, 'description' => 'إخراج مخزون مرتجع - '.$label];
        }

        if ($tax > 0) {
            $lines[] = ['role' => 'input_vat', 'credit' => $tax,
                        'description' => 'عكس ضريبة مشتريات - '.$label];
        }

        // What the supplier credits, against what the goods cost us.
        $difference = round($credit - $cost, 2);

        if (abs($difference) > self::EPSILON) {
            $lines[] = $difference > 0
                ? ['role' => 'other_expense', 'credit' => $difference,
                   'description' => 'فرق تسوية مردود لصالحنا - '.$label]
                : ['role' => 'other_expense', 'debit' => abs($difference),
                   'description' => 'فرق تسوية مردود علينا - '.$label];
        }

        return $this->post(
            key: $return->postingKey(),
            date: $return->return_date ? (string) $return->return_date->toDateString() : now()->toDateString(),
            description: 'إثبات '.$label,
            lines: $lines,
            reference: $return,
            module: 'purchases',
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
     *
     * @param  ?string  $key  overrides the default `expense:{id}` key, for an
     *                        expense being restated after its original entry
     *                        was reversed — that key now belongs to the
     *                        reversal, so re-posting under it would hand back
     *                        the reversed header instead of writing a new one.
     */
    public function postExpense($expense, ?string $key = null): ?JournalEntryHeader
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
            key: $key ?? ('expense:' . $expense->id),
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

        return $original ? $this->reverseEntry($original, $date) : null;
    }

    /**
     * Reverses a specific entry.
     *
     * Separate from `reverseFor` because an entry does not have to have been
     * produced by a document to be reversible: a manual entry typed into the
     * journal screen is corrected the same way a posted invoice is, and older
     * entries predate `posting_key` entirely. Those fall back to a key derived
     * from the entry's own id, which is just as unique.
     */
    public function reverseEntry(JournalEntryHeader $original, ?string $date = null): ?JournalEntryHeader
    {
        if ($original->status === 'reversed') {
            return null;
        }

        $original->loadMissing('lines.ledgerAccount');

        $postingKey = $original->posting_key ?: ('entry:'.$original->id);

        $lines = $original->lines->map(fn (JournalEntryLine $line) => [
            'account_id' => $line->account_id,
            // Swap the sides.
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'description' => 'عكس: ' . ($line->description ?? ''),
            // The reversal belongs to whoever the original line did, or an
            // employee's account statement would show the charge and not the
            // entry that cancelled it.
            'employee_id' => $line->employee_id,
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

        // Checked before anything is written, and after the idempotency check:
        // re-firing an event whose entry already exists must stay a no-op even
        // once its period is closed, or replaying a webhook or re-saving a
        // document would start failing on history that is already correct.
        $this->guardOpenPeriod($date);

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
                'employee_id' => $line['employee_id'] ?? null,
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

            $base = $this->baseCurrencyCode();

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
                // The document's own label, kept for tracing. It used to fall
                // back to the literal 'SAR' regardless of what the books were
                // actually kept in, which is how entries came to claim a
                // currency nobody had configured.
                'currency' => $currency ?: $base,
                // What the amounts are really in. Stamped per entry because the
                // base can be changed later, and an entry posted under the old
                // one must not start claiming the new.
                'base_currency' => $base,
                // Nothing converts on the way into the ledger — see
                // CurrencyService, which converts for display only — so this is
                // 1 by fact, not by omission.
                'exchange_rate' => 1,
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
                    'employee_id' => $line['employee_id'],
                ]);

                $account = LedgerAccount::find($line['account_id']);
                $delta = LedgerAccount::signedDelta($account->type, $line['debit'], $line['credit']);
                $account->increment('balance', $delta);
            }

            return $header;
        });
    }

    /**
     * Refuses to write into a period somebody has finished with.
     *
     * Without this, any document dated into a reported month — an invoice
     * backdated by a typo, a stock count entered late, a hand-typed entry —
     * changed statements that had already been printed and sent, and nothing
     * anywhere said so.
     *
     * @throws ClosedPeriodException
     */
    private function guardOpenPeriod(string $date): void
    {
        $period = AccountingPeriod::closedFor($date);

        if ($period) {
            throw new ClosedPeriodException($date, $period);
        }
    }

    /**
     * The currency the books are kept in.
     *
     * Resolved once per request and tolerant of a system where currencies were
     * never configured: posting must not fail because a lookup table is empty.
     */
    private function baseCurrencyCode(): string
    {
        return $this->baseCurrency ??= (function (): string {
            try {
                return app(CurrencyService::class)->baseCode();
            } catch (\Throwable) {
                return 'USD';
            }
        })();
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
