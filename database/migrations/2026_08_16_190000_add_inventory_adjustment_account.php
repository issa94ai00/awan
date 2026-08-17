<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The account a stock count writes its difference to.
 *
 * Inventory adjustments — a cycle count, damaged goods, a correction typed
 * into the stock screen — moved the warehouse and never touched the ledger.
 * The inventory asset therefore only ever changed for receipts and sales, and
 * drifted from the shelf by the whole of every count that ever found a
 * difference. Nothing reported the gap, because both records looked internally
 * consistent.
 *
 * One account takes both directions. A shortage debits it and a surplus
 * credits it, so the income statement carries the net result of counting for
 * the period as a single line, instead of a loss in one account and a gain
 * hidden in another where neither can be read against the other.
 *
 * Keyed on the code and skipped when present, so it is safe on a chart that
 * was built by hand.
 */
return new class extends Migration
{
    private const CODE = '5007';

    private const ROLE = 'inventory_adjustment';

    public function up(): void
    {
        $existingId = DB::table('ledger_accounts')->where('code', self::CODE)->value('id');
        $roleTaken = DB::table('ledger_accounts')->where('posting_role', self::ROLE)->exists();

        if ($existingId) {
            // The account is there but untagged: claim the role, unless some
            // other account already holds it (the column is unique).
            if (! $roleTaken) {
                DB::table('ledger_accounts')->where('id', $existingId)->update([
                    'posting_role' => self::ROLE,
                    'is_system' => 1,
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        if ($roleTaken) {
            return;
        }

        DB::table('ledger_accounts')->insert([
            'code' => self::CODE,
            'parent_id' => DB::table('ledger_accounts')->where('code', '5000')->value('id'),
            'name' => 'عجز وزيادة المخزون',
            'type' => 'expense',
            'account_type' => 'expense',
            'posting_role' => self::ROLE,
            'currency' => 'SAR',
            'balance' => 0,
            'opening_balance' => 0,
            'is_active' => 1,
            'is_system' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Only if it never took part in a posting; deleting an account that has
        // lines would orphan a journal entry and unbalance the books.
        $id = DB::table('ledger_accounts')->where('code', self::CODE)->value('id');

        if ($id && ! DB::table('journal_entry_lines')->where('account_id', $id)->exists()) {
            DB::table('ledger_accounts')->where('id', $id)->delete();
        }
    }
};
