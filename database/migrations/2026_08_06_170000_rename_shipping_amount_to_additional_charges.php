<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the invoice's delivery field to cover every charge billed on top of
 * the goods.
 *
 * The invoice form already lets a user attach charges to an invoice — shipping,
 * packaging, handling or other — and InvoiceController adds their total into
 * `total`. It just never stored that amount anywhere, which is what left
 * subtotal + tax - discount short of total and produced a journal entry that
 * credited less revenue than it debited receivables.
 *
 * `shipping_amount` was introduced hours earlier for the shipping case alone;
 * naming it for only one of the four categories would leave the other three
 * leaking the same way, so it becomes `additional_charges` before any real data
 * depends on the narrower name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'shipping_amount') && !Schema::hasColumn('invoices', 'additional_charges')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->renameColumn('shipping_amount', 'additional_charges');
            });
        } elseif (!Schema::hasColumn('invoices', 'additional_charges')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->decimal('additional_charges', 12, 2)->default(0)->after('discount');
            });
        }

        // The revenue account covers the same widened meaning. Charges recharged
        // to the customer land here as income; the matching costs keep their own
        // expense accounts (5002/5003/5006) so each category's margin stays visible.
        DB::table('ledger_accounts')
            ->where('code', '4004')
            ->update([
                'name' => 'إيرادات الشحن والخدمات',
                'posting_role' => 'additional_charges_revenue',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'additional_charges')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->renameColumn('additional_charges', 'shipping_amount');
            });
        }

        DB::table('ledger_accounts')
            ->where('code', '4004')
            ->update([
                'name' => 'إيرادات الشحن والتوصيل',
                'posting_role' => 'shipping_revenue',
                'updated_at' => now(),
            ]);
    }
};
