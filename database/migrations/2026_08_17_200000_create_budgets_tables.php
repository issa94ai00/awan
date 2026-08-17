<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the business intended to spend and earn, against what it did.
 *
 * Every report in this system so far answers "what happened". None of them can
 * say whether what happened was what was meant to happen. An expense account
 * showing 40,000 for the quarter is a fact with no verdict attached: it is
 * either well under control or a serious overrun, and only a figure set in
 * advance can tell the difference.
 *
 * A budget here is per account and per month of a year. Monthly rather than
 * annual because spending is rarely even — rent is the same every month,
 * marketing is not — and an annual figure divided by twelve produces variances
 * that are really just seasonality.
 *
 * The comparison is drawn from the ledger, not entered alongside it: the actual
 * side of a budget report is the same figure the income statement uses, so the
 * two can never disagree about what was spent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One budget per year per name, so a revision is a new budget
            // rather than a silent overwrite of the figures already reported on.
            $table->unique(['fiscal_year', 'name']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            // One figure per account per month; setting it again replaces it.
            $table->unique(['budget_id', 'account_id', 'month'], 'budget_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
