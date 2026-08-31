<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Always the first day of the month this record covers.
            $table->date('month');
            $table->decimal('commission_rate', 5, 2)->default(0);
            // Snapshot of recognised sales for the month — recomputed from
            // invoices on demand rather than kept live, so a past statement
            // does not silently reshuffle itself as later invoices post.
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('extra_expenses', 12, 2)->default(0);
            $table->decimal('withdrawals', 12, 2)->default(0);
            $table->decimal('monthly_target', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_commissions');
    }
};
