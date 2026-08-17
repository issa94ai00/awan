<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periods that can be closed, so a reported month stops moving.
 *
 * Nothing in the system distinguished a date that had been reported on from
 * any other. An invoice, a payment, a stock adjustment or a hand-typed journal
 * entry could be dated into last quarter and posted today, and every statement
 * anyone had already printed, sent or filed for that quarter silently stopped
 * matching the books it came from. There was no way to notice: the trial
 * balance still balanced, because a backdated entry is a perfectly valid entry
 * — just not in a period anybody is still allowed to change.
 *
 * A period here is a date range with a state:
 *
 *  - **open** — the normal state; postings dated inside it are accepted.
 *  - **closed** — reported on; postings dated inside it are refused. Reopening
 *    is deliberate, recorded, and available to whoever closed it.
 *
 * Dates not covered by any period stay open. That is what makes this safe to
 * add to a system with years of history behind it: nothing changes until a
 * period is actually created and closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Every posting asks the same question — is this date inside a
            // closed period — so the range is what carries the index.
            $table->index(['status', 'start_date', 'end_date']);
            $table->unique(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
