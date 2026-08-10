<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An inventory account per warehouse, under the shared 1005 parent.
 *
 * A sale can be filled from more than one place — part from the seller's own
 * stock, the remainder from the main warehouse. With a single inventory
 * account both credits landed in the same balance, so the books could say what
 * the sale cost but never which stock it came out of. That makes it impossible
 * to value a branch's holding, or to judge whether a seller is trading their
 * own goods or living off the central store.
 *
 * Accounts are keyed to the warehouse by `ledger_accounts.warehouse_id`.
 * Posting falls back to the generic `inventory` role when a warehouse has no
 * account of its own, so nothing breaks for installations that never split.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('ledger_accounts', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('posting_role')
                    ->constrained('warehouses')->nullOnDelete();
            }
        });

        $parent = DB::table('ledger_accounts')->where('code', '1005')->first();
        if (!$parent) {
            return;
        }

        $now = now();

        foreach (DB::table('warehouses')->orderBy('id')->get() as $warehouse) {
            $code = '1005-' . $warehouse->id;

            if (DB::table('ledger_accounts')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('ledger_accounts')->insert([
                'code' => $code,
                'parent_id' => $parent->id,
                'name' => 'مخزون - ' . $warehouse->name,
                'type' => 'asset',
                'account_type' => 'asset',
                // No posting_role: the column is unique, and these are resolved
                // by warehouse rather than by role.
                'posting_role' => null,
                'warehouse_id' => $warehouse->id,
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
        // Only accounts that never carried a posting may go; deleting one with
        // lines would orphan a journal entry.
        $ids = DB::table('ledger_accounts')->where('code', 'like', '1005-%')->pluck('id');
        $used = DB::table('journal_entry_lines')->whereIn('account_id', $ids)->pluck('account_id')->unique();

        DB::table('ledger_accounts')->whereIn('id', $ids->diff($used))->delete();

        Schema::table('ledger_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_accounts', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });
    }
};
