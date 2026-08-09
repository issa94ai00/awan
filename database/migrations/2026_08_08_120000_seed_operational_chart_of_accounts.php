<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Creates the operating chart of accounts the posting service expects.
 *
 * `2026_08_06_150000_add_accounting_posting_foundation` introduced the
 * `posting_role` column and tagged accounts 1001-5005 with their roles — but it
 * only ever ran an UPDATE, on the assumption that those accounts already
 * existed. On a database where they never were created (this one had five
 * accounts, all equity), every role except `capital`, `retained_earnings`,
 * `additional_charges_revenue` and `other_expense` resolved to nothing, and
 * LedgerPostingService refuses to write a lopsided entry — so invoices, payments
 * and credit notes could not reach the ledger at all.
 *
 * Two roles are new here because nothing had needed them yet:
 *
 *  - `inventory` (1005), the asset that goods sit in until they are sold.
 *  - `unearned_revenue` is deliberately *not* added; revenue is recognised when
 *    the invoice is issued, so there is nothing to defer.
 *
 * Everything is keyed on `code` and skipped when present, so this is safe to run
 * against a database that already has a partial or hand-built chart.
 */
return new class extends Migration
{
    /**
     * code => [name, type, parent code, posting role]
     *
     * `type` drives the normal balance side (see LedgerAccount::signedDelta),
     * so it must be one of asset/liability/equity/revenue/expense.
     */
    private const ACCOUNTS = [
        // Assets
        '1000' => ['الأصول', 'asset', null, null],
        '1001' => ['الصندوق', 'asset', '1000', 'cash'],
        '1002' => ['البنك', 'asset', '1000', 'bank'],
        '1003' => ['ذمم العملاء المدينة', 'asset', '1000', 'accounts_receivable'],
        '1004' => ['سلف الموظفين', 'asset', '1000', 'employee_advances'],
        '1005' => ['المخزون', 'asset', '1000', 'inventory'],

        // Liabilities
        '2000' => ['الالتزامات', 'liability', null, null],
        '2001' => ['ضريبة القيمة المضافة المستحقة', 'liability', '2000', 'tax_payable'],
        '2002' => ['ذمم الموردين الدائنة', 'liability', '2000', 'accounts_payable'],

        // Revenue
        '4000' => ['الإيرادات', 'revenue', null, null],
        '4001' => ['إيرادات المبيعات', 'revenue', '4000', 'sales_revenue'],
        '4002' => ['مردودات المبيعات', 'revenue', '4000', 'sales_returns'],
        '4003' => ['خصومات المبيعات', 'revenue', '4000', 'sales_discounts'],

        // Expenses
        '5000' => ['المصروفات', 'expense', null, null],
        '5001' => ['تكلفة البضاعة المباعة', 'expense', '5000', 'cogs'],
        '5002' => ['مصروف الشحن', 'expense', '5000', 'shipping_expense'],
        '5003' => ['مصروف التغليف', 'expense', '5000', 'packaging_expense'],
        '5004' => ['الرواتب والأجور', 'expense', '5000', 'salaries_expense'],
        '5005' => ['العمولات', 'expense', '5000', 'commissions_expense'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ACCOUNTS as $code => [$name, $type, $parentCode, $role]) {
            $existingId = DB::table('ledger_accounts')->where('code', $code)->value('id');

            if ($existingId) {
                // The account is there but was never tagged — claim the role,
                // unless another account already holds it (the column is unique).
                if ($role && !DB::table('ledger_accounts')->where('posting_role', $role)->exists()) {
                    DB::table('ledger_accounts')
                        ->where('id', $existingId)
                        ->update(['posting_role' => $role, 'is_system' => 1, 'updated_at' => $now]);
                }

                continue;
            }

            // A role taken by some other account means this chart is already
            // wired differently; adding a second holder would break the unique
            // index and silently split the postings across two accounts.
            $roleToSet = ($role && DB::table('ledger_accounts')->where('posting_role', $role)->exists())
                ? null
                : $role;

            DB::table('ledger_accounts')->insert([
                'code' => $code,
                'parent_id' => $parentCode
                    ? DB::table('ledger_accounts')->where('code', $parentCode)->value('id')
                    : null,
                'name' => $name,
                'type' => $type,
                'account_type' => $type,
                'posting_role' => $roleToSet,
                'currency' => 'SAR',
                'balance' => 0,
                'opening_balance' => 0,
                'is_active' => 1,
                'is_system' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Only remove accounts that never took part in a posting; deleting one
        // that has lines would orphan a journal entry and unbalance the books.
        $codes = array_keys(self::ACCOUNTS);

        $ids = DB::table('ledger_accounts')->whereIn('code', $codes)->pluck('id');

        $used = DB::table('journal_entry_lines')->whereIn('account_id', $ids)->pluck('account_id')->unique();

        DB::table('ledger_accounts')
            ->whereIn('id', $ids->diff($used))
            ->delete();
    }
};
