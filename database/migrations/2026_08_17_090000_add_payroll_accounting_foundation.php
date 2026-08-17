<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What payroll needs before it can reach the books.
 *
 * The chart has carried a salaries expense account (5004) since the posting
 * foundation was laid, and nothing has ever posted to it: the payroll module
 * computes, stores and pays salaries entirely outside the ledger. So the
 * largest recurring cost most businesses have appears in no income statement
 * the system produces, and the cash it consumes leaves the books unexplained.
 *
 * Two accounts are missing for the accrual, which is the half that makes the
 * timing right — the cost belongs to the period worked, not the day the
 * transfer happens:
 *
 *  - **2003 Salaries payable** holds the net wage between the moment it is
 *    earned and the moment it is paid.
 *  - **2004 Payroll deductions payable** holds whatever was withheld. The
 *    payroll record carries a single untyped `deductions` figure, so this
 *    stays a liability rather than guessing whether a particular deduction
 *    repaid an advance, settled a penalty, or is owed to a third party.
 *    Reclassifying it is a decision for whoever knows, made with a journal
 *    entry, not one this migration should make silently.
 *
 * And a column: `payment_method`, because crediting the bank for a salary
 * handed over in cash puts the shortfall in the wrong account and leaves the
 * till overstated for as long as nobody reconciles it.
 */
return new class extends Migration
{
    /** code => [name, posting role] — all liabilities under 2000. */
    private const ACCOUNTS = [
        '2003' => ['رواتب مستحقة الدفع', 'salaries_payable'],
        '2004' => ['استقطاعات رواتب مستحقة', 'payroll_deductions_payable'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('payrolls', 'payment_method')) {
            Schema::table('payrolls', function (Blueprint $table) {
                // Cash by default: it is what this business pays wages in, and
                // a default that matches reality is corrected less often than
                // one that does not.
                $table->string('payment_method', 30)->default('cash')->after('payment_date');
            });
        }

        $parentId = DB::table('ledger_accounts')->where('code', '2000')->value('id');
        $now = now();

        foreach (self::ACCOUNTS as $code => [$name, $role]) {
            $existingId = DB::table('ledger_accounts')->where('code', $code)->value('id');
            $roleTaken = DB::table('ledger_accounts')->where('posting_role', $role)->exists();

            if ($existingId) {
                // Present but untagged: claim the role unless another account
                // already holds it — the column is unique across the chart.
                if (! $roleTaken) {
                    DB::table('ledger_accounts')->where('id', $existingId)->update([
                        'posting_role' => $role,
                        'is_system' => 1,
                        'updated_at' => $now,
                    ]);
                }

                continue;
            }

            if ($roleTaken) {
                continue;
            }

            DB::table('ledger_accounts')->insert([
                'code' => $code,
                'parent_id' => $parentId,
                'name' => $name,
                'type' => 'liability',
                'account_type' => 'liability',
                'posting_role' => $role,
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
        // Only accounts that never took part in a posting; removing one that
        // has lines would orphan a journal entry and unbalance the books.
        $ids = DB::table('ledger_accounts')->whereIn('code', array_keys(self::ACCOUNTS))->pluck('id');
        $used = DB::table('journal_entry_lines')->whereIn('account_id', $ids)->pluck('account_id')->unique();

        DB::table('ledger_accounts')->whereIn('id', $ids->diff($used))->delete();

        if (Schema::hasColumn('payrolls', 'payment_method')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }
    }
};
