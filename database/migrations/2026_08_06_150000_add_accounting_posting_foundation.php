<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation for automatic posting to the general ledger.
 *
 * Three things were missing before business documents could feed the ledger:
 *
 * 1. Equity accounts. The chart had assets, liabilities, revenue and expenses
 *    but no equity at all, so the accounting equation (A = L + E) could never
 *    hold and the balance sheet reported `is_balanced: false` by construction.
 *
 * 2. A stable way to name an account by its role. Posting code that hardcodes
 *    account codes breaks the moment somebody renumbers the chart, so each
 *    account that the system posts to gets a `posting_role` and the code looks
 *    accounts up by role.
 *
 * 3. Idempotency and reversal on the journal header. `posting_key` makes a
 *    document post exactly once no matter how often the event fires, and
 *    `reversal_of_id` links a reversing entry back to what it cancels.
 */
return new class extends Migration
{
    /**
     * Existing accounts mapped to the role the posting service asks for.
     * Keyed by the code already present in the chart of accounts.
     */
    private const ROLE_BY_CODE = [
        '1001' => 'cash',
        '1002' => 'bank',
        '1003' => 'accounts_receivable',
        '1004' => 'employee_advances',
        '2001' => 'tax_payable',
        '2002' => 'accounts_payable',
        '4001' => 'sales_revenue',
        '4002' => 'sales_returns',
        '4003' => 'sales_discounts',
        '5001' => 'cogs',
        '5002' => 'shipping_expense',
        '5003' => 'packaging_expense',
        '5004' => 'salaries_expense',
        '5005' => 'commissions_expense',
    ];

    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('ledger_accounts', 'posting_role')) {
                $table->string('posting_role', 60)->nullable()->unique()->after('account_type');
            }
        });

        Schema::table('journal_entry_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('journal_entry_headers', 'posting_key')) {
                // Natural key of the event that produced this entry, e.g.
                // "invoice:12" or "payment:7:reversal". Unique, so a repeated
                // event cannot double-post.
                $table->string('posting_key', 191)->nullable()->unique()->after('reference_id');
            }
            if (!Schema::hasColumn('journal_entry_headers', 'source_module')) {
                $table->string('source_module', 40)->nullable()->after('posting_key');
            }
            if (!Schema::hasColumn('journal_entry_headers', 'reversal_of_id')) {
                $table->foreignId('reversal_of_id')->nullable()->after('source_module')
                    ->constrained('journal_entry_headers')->nullOnDelete();
            }
        });

        $now = now();

        // Equity branch. Retained earnings is where the closed result of each
        // period lands; until a period is closed the balance sheet shows the
        // running result separately (see AccountingReportController).
        $equity = [
            ['code' => '3000', 'name' => 'حقوق الملكية', 'role' => null, 'parent' => null],
            ['code' => '3001', 'name' => 'رأس المال', 'role' => 'capital', 'parent' => '3000'],
            ['code' => '3002', 'name' => 'الأرباح المحتجزة', 'role' => 'retained_earnings', 'parent' => '3000'],
        ];

        foreach ($equity as $account) {
            if (DB::table('ledger_accounts')->where('code', $account['code'])->exists()) {
                continue;
            }

            $parentId = $account['parent']
                ? DB::table('ledger_accounts')->where('code', $account['parent'])->value('id')
                : null;

            DB::table('ledger_accounts')->insert([
                'code' => $account['code'],
                'parent_id' => $parentId,
                'name' => $account['name'],
                'type' => 'equity',
                'account_type' => 'equity',
                'posting_role' => $account['role'],
                'currency' => 'SAR',
                'balance' => 0,
                'opening_balance' => 0,
                'is_active' => 1,
                'is_system' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // A catch-all operating expense so the expenses module always has a
        // destination, rather than silently failing to post.
        if (!DB::table('ledger_accounts')->where('code', '5006')->exists()) {
            DB::table('ledger_accounts')->insert([
                'code' => '5006',
                'parent_id' => DB::table('ledger_accounts')->where('code', '5000')->value('id'),
                'name' => 'مصروفات تشغيلية أخرى',
                'type' => 'expense',
                'account_type' => 'expense',
                'posting_role' => 'other_expense',
                'currency' => 'SAR',
                'balance' => 0,
                'opening_balance' => 0,
                'is_active' => 1,
                'is_system' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::ROLE_BY_CODE as $code => $role) {
            DB::table('ledger_accounts')
                ->where('code', $code)
                ->whereNull('posting_role')
                ->update(['posting_role' => $role, 'is_system' => 1, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('ledger_accounts')->whereIn('code', ['3000', '3001', '3002', '5006'])->delete();

        Schema::table('journal_entry_headers', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entry_headers', 'reversal_of_id')) {
                $table->dropConstrainedForeignId('reversal_of_id');
            }
            foreach (['posting_key', 'source_module'] as $column) {
                if (Schema::hasColumn('journal_entry_headers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('ledger_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_accounts', 'posting_role')) {
                $table->dropUnique(['posting_role']);
                $table->dropColumn('posting_role');
            }
        });
    }
};
