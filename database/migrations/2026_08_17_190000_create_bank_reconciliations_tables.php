<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proving the bank account against what the bank says.
 *
 * The ledger's bank balance and the bank's own statement disagree almost every
 * day, and legitimately: a cheque written on the 28th clears on the 3rd, a
 * transfer deposited on Thursday lands on Sunday. That gap is normal. What is
 * not normal is having no way to tell that gap apart from a payment recorded
 * twice, a transfer that never arrived, or a bank charge nobody entered.
 *
 * Without a reconciliation the bank account is the one balance in the books
 * that has an independent witness and is never asked. A reconciliation asks it:
 * every movement is either **cleared** — the bank has seen it too — or still
 * outstanding, and the arithmetic has to close:
 *
 *     book balance − still outstanding = statement balance
 *
 * When that holds, every difference is explained by timing. When it does not,
 * the remainder is an error in one of the two records, and the reconciliation
 * refuses to be completed until somebody finds it.
 *
 * Cleared lines are recorded in their own table rather than by flagging
 * `journal_entry_lines`: a posted line is not amended, and a line's clearing is
 * a fact about a reconciliation rather than about the entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Which account is being proved — the bank, or any account that has
            // an outside statement to be held against.
            $table->foreignId('account_id')->constrained('ledger_accounts')->restrictOnDelete();

            $table->date('statement_date');
            $table->decimal('statement_balance', 15, 2);

            $table->string('status', 20)->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'statement_date']);
        });

        Schema::create('bank_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_line_id')->constrained('journal_entry_lines')->cascadeOnDelete();
            $table->timestamps();

            // A line clears once within a reconciliation.
            $table->unique(['bank_reconciliation_id', 'journal_entry_line_id'], 'bank_recon_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_lines');
        Schema::dropIfExists('bank_reconciliations');
    }
};
