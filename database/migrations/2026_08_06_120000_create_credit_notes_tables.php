<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit notes — the document that was missing from the returns settlement.
 *
 * Without one, crediting a customer for returned goods had no correct home:
 * reducing an invoice's paid_amount made a fully-refunded invoice look unpaid,
 * and reducing its total rewrote an issued document. A credit note records the
 * obligation separately and is then consumed in one or more of three ways —
 * offset against the invoice's outstanding balance, paid back in cash, or left
 * on the customer's account as store credit. Those three amounts are tracked on
 * the note itself so the settlement is auditable without a separate
 * allocations table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number')->unique();

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // Both nullable: a credit note can be raised without an invoice
            // (goodwill) or without a return (manual adjustment).
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('rma_request_id')->nullable()->constrained('rma_requests')->nullOnDelete();

            $table->date('issue_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // How the credit has been consumed. These three always sum to the
            // amount settled; total minus their sum is what is still open.
            $table->decimal('applied_to_invoice', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->decimal('store_credit_amount', 12, 2)->default(0);

            $table->enum('status', ['issued', 'partially_applied', 'applied', 'cancelled'])
                ->default('issued');

            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index('issue_date');
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            // Ties each credited line back to the returned line it came from.
            $table->foreignId('rma_item_id')->nullable()->constrained('rma_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
    }
};
