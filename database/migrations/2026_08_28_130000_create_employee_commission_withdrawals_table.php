<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_commission_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_commission_id')->constrained()->cascadeOnDelete();
            $table->dateTime('withdrawn_at');
            $table->string('currency_code', 10);
            // Both kept as snapshots: the rate moves, and a past withdrawal
            // must keep reading at the rate that was actually in force when
            // the cash left, not whatever the table quotes today.
            $table->decimal('amount', 14, 2);
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->decimal('base_amount', 14, 2);
            $table->string('method', 20)->default('cash');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_commission_id', 'withdrawn_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_commission_withdrawals');
    }
};
