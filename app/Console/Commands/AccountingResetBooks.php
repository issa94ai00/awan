<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Empties the books and leaves a baseline to start from.
 *
 * For the moment a business goes live: the ledger is carrying entries from
 * trials and experiments, party balances were typed in by hand while the
 * modules were being tested, and none of it describes anything real. Carrying
 * that forward means every statement is wrong from the first day and nobody
 * can tell which figures were meant.
 *
 * What this removes is financial: journal entries, the documents that produce
 * them, and the balances they maintain. What it deliberately does not touch is
 * operational — products, warehouses, stock quantities, cost layers, users and
 * settings all survive, because a live business keeps its catalogue and its
 * shelves. That split is also why `accounting:opening-balance` exists: stock
 * left on the shelf with no value in the books is only half a clean start.
 *
 * Destructive and irreversible. It asks before doing anything unless --force.
 */
class AccountingResetBooks extends Command
{
    protected $signature = 'accounting:reset-books
                            {--force : Skip the confirmation prompt}
                            {--keep-parties : Do not create the general customer and supplier}';

    protected $description = 'Wipe the ledger and financial documents, then leave a clean baseline';

    /**
     * Financial documents, in an order that respects the foreign keys between
     * them: children before parents, and everything that points at a journal
     * entry before the entries themselves.
     */
    private const DOCUMENT_TABLES = [
        'journal_entry_lines',
        'journal_entry_headers',
        'payments',
        'supplier_payments',
        'credit_note_items',
        'credit_notes',
        'invoice_items',
        'invoices',
        'purchase_receipt_items',
        'purchase_receipts',
        'expenses',
        'payrolls',
    ];

    public function handle(): int
    {
        $counts = $this->currentCounts();

        $this->warn('سيتم حذف الآتي نهائياً:');
        $this->table(['الجدول', 'عدد السجلات'], collect($counts)
            ->filter(fn ($n) => $n > 0)
            ->map(fn ($n, $table) => [$table, $n])
            ->values()
            ->all());

        $this->line('وسيُصفَّر رصيد '.$this->accountsWithBalance().' حساب في دليل الحسابات، وأرصدة العملاء والموردين.');
        $this->newLine();
        $this->info('لن يُمس: المنتجات، المستودعات، كميات المخزون وطبقات التكلفة، المستخدمون والإعدادات.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('متابعة؟', false)) {
            $this->line('أُلغيت العملية.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            $this->wipeDocuments();
            $this->zeroBalances();

            // A period closed over entries that no longer exist would refuse
            // every posting into dates the new books are about to use.
            AccountingPeriod::query()->delete();
        });

        if (! $this->option('keep-parties')) {
            $this->seedGeneralParties();
        }

        $this->newLine();
        $this->info('تمت تصفية الدفاتر.');
        $this->line('الخطوة التالية: php artisan accounting:opening-balance --inventory');
        $this->line('(المخزون ما زال على الرفوف وقيمته صفر في الدفاتر حتى يُرحَّل القيد الافتتاحي.)');

        return self::SUCCESS;
    }

    /** @return array<string,int> */
    private function currentCounts(): array
    {
        $counts = [];

        foreach (self::DOCUMENT_TABLES as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    private function accountsWithBalance(): int
    {
        return DB::table('ledger_accounts')->whereRaw('ABS(COALESCE(balance,0)) > 0.005')->count();
    }

    private function wipeDocuments(): void
    {
        // Constraint checks are off for the duration: these tables reference
        // each other in both directions (an invoice points at a sales order,
        // a journal entry points back at the document that produced it), so
        // there is no single delete order that satisfies every one of them.
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach (self::DOCUMENT_TABLES as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function zeroBalances(): void
    {
        DB::table('ledger_accounts')->update(['balance' => 0, 'opening_balance' => 0]);

        foreach (['customers', 'suppliers'] as $table) {
            if (DB::getSchemaBuilder()->hasColumn($table, 'balance')) {
                DB::table($table)->update(['balance' => 0]);
            }
        }

        // Running totals on the party records summarise documents that no
        // longer exist, so they have to go the same way as the balances.
        foreach ([['customers', 'total_purchases'], ['suppliers', 'total_purchases']] as [$table, $column]) {
            if (DB::getSchemaBuilder()->hasColumn($table, $column)) {
                DB::table($table)->update([$column => 0]);
            }
        }

        if (DB::getSchemaBuilder()->hasColumn('customers', 'last_purchase_at')) {
            DB::table('customers')->update(['last_purchase_at' => null]);
        }
    }

    /**
     * The counterparties a transaction falls back on.
     *
     * A counter sale to somebody who will never be a named account, or a
     * purchase from a one-off vendor, still has to be attributed to someone —
     * receivables and payables are balances *of a party*. Without a general
     * record, the field is left empty and the document drops out of every
     * statement of who owes what.
     */
    private function seedGeneralParties(): void
    {
        $customer = Customer::firstOrCreate(
            ['name' => 'عميل عام'],
            [
                'email' => null,
                'phone' => null,
                'status' => 'active',
                'balance' => 0,
                'notes' => 'طرف افتراضي للمبيعات النقدية التي لا تخص عميلاً مسجّلاً.',
            ]
        );

        $supplier = Supplier::firstOrCreate(
            ['name' => 'مورد عام'],
            [
                'status' => 'active',
                'balance' => 0,
                'notes' => 'طرف افتراضي للمشتريات التي لا تخص مورّداً مسجّلاً.',
            ]
        );

        $this->line('العميل العام: #'.$customer->id.'  |  المورد العام: #'.$supplier->id);
    }
}
