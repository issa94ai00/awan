<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The wage bill that is earned every month and paid only at the end.
 *
 * An end-of-service benefit accrues while somebody works: each month of
 * service adds to what they will be owed the day they leave. Recognising it
 * only on the day they leave puts years of cost into one month, and — worse —
 * leaves the balance sheet silent about a debt the business has already
 * incurred. A company can look solvent while owing its staff a year of wages.
 *
 *   monthly   Dr  End-of-service expense    the month's share
 *                 Cr  End-of-service payable     what has built up
 *   on leaving Dr  End-of-service payable   what was owed
 *                 Cr  Cash/Bank                  what was paid
 *
 * Also adds `deduction_type` to payrolls. Deductions were all held on one
 * neutral liability because the record gave no way to tell them apart; saying
 * a deduction is an advance being repaid lets it reduce the advance instead —
 * which is what actually happened, and keeps the employee-advances asset from
 * standing forever against money that has already come back.
 */
return new class extends Migration
{
    private const ACCOUNTS = [
        '2005' => ['مكافأة نهاية الخدمة المستحقة', 'liability', '2000', 'end_of_service_payable'],
        '5010' => ['مصروف مكافأة نهاية الخدمة', 'expense', '5000', 'end_of_service_expense'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('payrolls', 'deduction_type')) {
            Schema::table('payrolls', function (Blueprint $table) {
                // `general` keeps every existing payroll behaving exactly as it
                // was posted: held on the neutral liability.
                $table->string('deduction_type', 20)->default('general')->after('deductions');
            });
        }

        // Tracks how much of an employee's benefit has been accrued and up to
        // when, so a run cannot charge the same month twice.
        if (! Schema::hasColumn('employees', 'end_of_service_accrued')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->decimal('end_of_service_accrued', 15, 2)->default(0)->after('salary');
                $table->date('end_of_service_through')->nullable()->after('end_of_service_accrued');
            });
        }

        $now = now();
        $base = DB::table('currencies')->where('is_base', 1)->value('code') ?: 'USD';

        foreach (self::ACCOUNTS as $code => [$name, $type, $parentCode, $role]) {
            $existingId = DB::table('ledger_accounts')->where('code', $code)->value('id');
            $roleTaken = DB::table('ledger_accounts')->where('posting_role', $role)->exists();

            if ($existingId) {
                if (! $roleTaken) {
                    // Never over an account that already answers to something:
                    // that mistake has been made here once already.
                    DB::table('ledger_accounts')->where('id', $existingId)->whereNull('posting_role')->update([
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
                'parent_id' => DB::table('ledger_accounts')->where('code', $parentCode)->value('id'),
                'name' => $name,
                'type' => $type,
                'account_type' => $type,
                'posting_role' => $role,
                'currency' => $base,
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
        $ids = DB::table('ledger_accounts')->whereIn('code', array_keys(self::ACCOUNTS))->pluck('id');
        $used = DB::table('journal_entry_lines')->whereIn('account_id', $ids)->pluck('account_id')->unique();

        DB::table('ledger_accounts')->whereIn('id', $ids->diff($used))->delete();

        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'deduction_type')) {
                $table->dropColumn('deduction_type');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            foreach (['end_of_service_accrued', 'end_of_service_through'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
