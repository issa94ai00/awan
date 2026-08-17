<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The purchase side of value-added tax.
 *
 * Sales have collected tax since the posting service was written: an invoice
 * credits 2001 with what was charged, because that money is owed to the state
 * rather than earned. Purchases had no equivalent. A receipt booked the entire
 * amount paid into inventory, which is wrong twice over — the goods are
 * carried at more than they cost, so every margin computed from them is
 * understated, and the tax paid to the supplier is recoverable but appears
 * nowhere, so the business quietly hands the state money it was entitled to
 * deduct.
 *
 * Two things are needed:
 *
 *  - **1006 Input VAT** — an asset, because it is a claim against the tax
 *    authority rather than a cost of doing business.
 *  - **`purchase_receipts.tax_amount`** — the tax on the document, kept apart
 *    from the goods it was charged on. Header-level, matching how the sales
 *    side already carries `invoices.tax`.
 *
 * Existing receipts keep a tax of zero, which is exactly how they were posted,
 * so nothing already in the ledger is contradicted by this.
 */
return new class extends Migration
{
    /**
     * 1007, not 1006: that code was taken by goods-in-transit, and an earlier
     * version of this migration claimed the free code it saw without checking
     * whether the account already had a role of its own. It overwrote one, and
     * transfers between warehouses stopped posting because the role they
     * resolve through no longer existed. `2026_08_17_120000` repairs any
     * database that ran that version.
     */
    private const CODE = '1007';

    private const ROLE = 'input_vat';

    public function up(): void
    {
        if (! Schema::hasColumn('purchase_receipts', 'tax_amount')) {
            Schema::table('purchase_receipts', function (Blueprint $table) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('receipt_date');
            });
        }

        $existingId = DB::table('ledger_accounts')->where('code', self::CODE)->value('id');
        $roleTaken = DB::table('ledger_accounts')->where('posting_role', self::ROLE)->exists();

        if ($existingId) {
            // Only an account that carries no role at all may be claimed:
            // overwriting one silently redirects every posting that resolved
            // through it, and the account it belonged to stops working with no
            // error until something tries to post.
            if (! $roleTaken) {
                DB::table('ledger_accounts')
                    ->where('id', $existingId)
                    ->whereNull('posting_role')
                    ->update([
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
            'parent_id' => DB::table('ledger_accounts')->where('code', '1000')->value('id'),
            'name' => 'ضريبة القيمة المضافة على المشتريات',
            'type' => 'asset',
            'account_type' => 'asset',
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
        $id = DB::table('ledger_accounts')->where('code', self::CODE)->value('id');

        if ($id && ! DB::table('journal_entry_lines')->where('account_id', $id)->exists()) {
            DB::table('ledger_accounts')->where('id', $id)->delete();
        }

        if (Schema::hasColumn('purchase_receipts', 'tax_amount')) {
            Schema::table('purchase_receipts', function (Blueprint $table) {
                $table->dropColumn('tax_amount');
            });
        }
    }
};
