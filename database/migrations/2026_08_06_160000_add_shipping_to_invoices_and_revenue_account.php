<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an invoice carry a delivery charge, and gives that charge somewhere to
 * land in the ledger.
 *
 * Invoices had no shipping field at all, so a delivery charge billed to the
 * customer could only be absorbed into `total` — leaving
 * subtotal + tax - discount ≠ total and a journal entry that credited less
 * revenue than it debited receivables. INV-20260727-DE03 is exactly that case:
 * 50,000 of goods, a linked 30,000 delivery expense, and an 80,000 total that
 * nothing in the schema could explain.
 *
 * Billing delivery to a customer is revenue, and it is deliberately kept
 * separate from the cost paid to the carrier (5002) so the margin on delivery
 * stays visible instead of the two netting to nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'shipping_amount')) {
                $table->decimal('shipping_amount', 12, 2)->default(0)->after('discount');
            }
        });

        if (!DB::table('ledger_accounts')->where('code', '4004')->exists()) {
            DB::table('ledger_accounts')->insert([
                'code' => '4004',
                'parent_id' => DB::table('ledger_accounts')->where('code', '4000')->value('id'),
                'name' => 'إيرادات الشحن والتوصيل',
                'type' => 'revenue',
                'account_type' => 'revenue',
                'posting_role' => 'shipping_revenue',
                'currency' => 'SAR',
                'balance' => 0,
                'opening_balance' => 0,
                'is_active' => 1,
                'is_system' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->backfillShippingFromLinkedExpenses();
    }

    /**
     * Recovers the delivery charge already hidden inside `total`.
     *
     * Only applied where the evidence is unambiguous: the invoice's unexplained
     * remainder must match its linked shipping expenses to the cent. Anything
     * else is left for a human — guessing at invoice figures is not the
     * migration's job.
     */
    private function backfillShippingFromLinkedExpenses(): void
    {
        $candidates = DB::table('invoices as i')
            ->where('i.shipping_amount', 0)
            ->selectRaw('i.id,
                         ROUND(i.total - (i.subtotal + i.tax - i.discount), 2) as unexplained,
                         COALESCE((SELECT SUM(e.amount) FROM expenses e
                                   WHERE e.invoice_id = i.id AND e.category = ?), 0) as shipping_cost', ['shipping'])
            ->get();

        foreach ($candidates as $invoice) {
            $unexplained = round((float) $invoice->unexplained, 2);
            $shippingCost = round((float) $invoice->shipping_cost, 2);

            if ($unexplained <= 0 || abs($unexplained - $shippingCost) > 0.005) {
                continue;
            }

            DB::table('invoices')->where('id', $invoice->id)->update([
                'shipping_amount' => $unexplained,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ledger_accounts')->where('code', '4004')->delete();

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
        });
    }
};
