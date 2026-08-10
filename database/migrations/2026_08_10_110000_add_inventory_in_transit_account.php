<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Goods in transit between two of the company's own warehouses.
 *
 * Stock that has left one warehouse but not yet arrived at the next belongs to
 * neither. With a single inventory account that never mattered — an internal
 * transfer did not change the company's total holding, so the books could
 * ignore it. Per-warehouse accounts make it matter: the source must be
 * credited when the goods leave and the destination debited when they arrive,
 * and something has to hold the value in between or it disappears from the
 * balance sheet for as long as the lorry is on the road.
 *
 *   ship     Dr Goods in transit   Cr Inventory — source
 *   receive  Dr Inventory — dest   Cr Goods in transit
 *
 * A shipment that never arrives leaves its value sitting here, which is
 * exactly the visibility a shrinkage investigation needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('ledger_accounts')->where('code', '1006')->exists()) {
            return;
        }

        DB::table('ledger_accounts')->insert([
            'code' => '1006',
            'parent_id' => DB::table('ledger_accounts')->where('code', '1000')->value('id'),
            'name' => 'بضاعة في الطريق',
            'type' => 'asset',
            'account_type' => 'asset',
            'posting_role' => 'inventory_in_transit',
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
        $id = DB::table('ledger_accounts')->where('code', '1006')->value('id');
        if ($id && !DB::table('journal_entry_lines')->where('account_id', $id)->exists()) {
            DB::table('ledger_accounts')->where('id', $id)->delete();
        }
    }
};
