<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the business pays its suppliers.
 *
 * The `payments` table was built around the customer side: it carries a
 * `customer_id` and nothing else, and `LedgerPostingService::postPayment`
 * always settles receivables with it. So money going out to a supplier had
 * nowhere to be recorded at all.
 *
 * The consequence was not a missing screen, it was a wrong balance sheet.
 * Receiving goods credits accounts payable on every purchase receipt, and
 * nothing in the system ever debited it back — the liability only grew, for
 * the life of the installation, no matter how many invoices had actually been
 * settled. Every figure that reads payables was wrong by the whole amount
 * paid: total liabilities, working capital, the accounting equation.
 *
 * Kept as its own table rather than a `supplier_id` column on `payments`
 * because the two are different documents that happen to share a shape: one
 * settles a receivable and touches a customer's balance, the other settles a
 * payable and touches a supplier's. Sharing the table would mean every
 * existing query that reads `payments` (collections, sales reports, the
 * customer statement, the ledger backfill) silently starts including money
 * that flowed the other way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();

            // Nullable on delete, like `payments.customer_id`: removing a
            // supplier must not take the record of what was paid with it, and
            // the journal entry behind it stays either way.
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('payment_method', 30);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('status', 20)->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The two questions this table is asked: what did we pay this
            // supplier, and what went out during the period.
            $table->index(['supplier_id', 'payment_date']);
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
