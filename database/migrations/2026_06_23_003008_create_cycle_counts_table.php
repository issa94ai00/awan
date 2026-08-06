<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cycle_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('warehouse_bins');
            $table->string('count_number')->unique();
            $table->enum('type', ['full', 'partial', 'abc', 'blind'])->default('partial');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('counter_id')->nullable()->constrained('users');
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->integer('total_items')->default(0);
            $table->integer('variance_items')->default(0);
            $table->decimal('variance_value', 15, 2)->default(0);
            $table->boolean('requires_adjustment')->default(false);
            $table->foreignId('adjustment_by')->nullable()->constrained('users');
            $table->timestamp('adjusted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['warehouse_id', 'status']);
            $table->index(['bin_id']);
            $table->index(['counter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cycle_counts');
    }
};
