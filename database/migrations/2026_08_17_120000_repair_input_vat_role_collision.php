<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives goods-in-transit its role back.
 *
 * The first version of `2026_08_17_110000` looked for account 1006, found one,
 * and — seeing that no account yet held `input_vat` — wrote that role onto it.
 * Account 1006 was already **goods in transit**, and the check it passed only
 * asked whether the *new* role was taken, never whether the account it was
 * about to relabel already had one of its own.
 *
 * Two things broke at once, and neither announced itself:
 *
 *  - `inventory_in_transit` stopped existing, so shipping a warehouse transfer
 *    threw "لا يوجد حساب مرتبط بالدور" and could not be posted at all.
 *  - tax paid on purchases would have landed in the goods-in-transit account,
 *    where nobody would think to look for it.
 *
 * This restores the original role and moves input VAT to 1007, its own
 * account. Journal lines are not touched: an account's identity is its code
 * and the entries that reference it, and re-pointing those would rewrite
 * history to cover a mapping mistake.
 *
 * Safe on a database that never ran the broken version — it finds 1006 already
 * correct and does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $misassigned = DB::table('ledger_accounts')
            ->where('code', '1006')
            ->where('posting_role', 'input_vat')
            ->first();

        if ($misassigned) {
            $inTransitTaken = DB::table('ledger_accounts')
                ->where('posting_role', 'inventory_in_transit')
                ->exists();

            DB::table('ledger_accounts')->where('id', $misassigned->id)->update([
                // Null rather than the old role if something else has claimed
                // it since: the column is unique, and a half-applied repair is
                // worse than one that stops and leaves the state visible.
                'posting_role' => $inTransitTaken ? null : 'inventory_in_transit',
                'updated_at' => now(),
            ]);
        }

        // Input VAT gets a code of its own, whether or not the repair above was
        // needed — a database that ran the broken version has no input VAT
        // account at all now.
        $hasInputVat = DB::table('ledger_accounts')->where('posting_role', 'input_vat')->exists();

        if (! $hasInputVat && ! DB::table('ledger_accounts')->where('code', '1007')->exists()) {
            DB::table('ledger_accounts')->insert([
                'code' => '1007',
                'parent_id' => DB::table('ledger_accounts')->where('code', '1000')->value('id'),
                'name' => 'ضريبة القيمة المضافة على المشتريات',
                'type' => 'asset',
                'account_type' => 'asset',
                'posting_role' => 'input_vat',
                'currency' => 'SAR',
                'balance' => 0,
                'opening_balance' => 0,
                'is_active' => 1,
                'is_system' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Deliberately not reversible: rolling back would mean re-breaking the
        // transfer postings.
    }
};
