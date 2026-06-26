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
        // Drop table if it exists (from database.sql import)
        if (Schema::hasTable('rma_requests')) {
            Schema::dropIfExists('rma_requests');
        }

        Schema::create('rma_requests', function (Blueprint $table) {
            $table->id();
            $table->string('rma_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled'])->default('pending');
            $table->enum('type', ['refund', 'exchange', 'store_credit'])->nullable();
            $table->enum('reason', ['defective', 'damaged', 'wrong_item', 'not_as_described', 'changed_mind', 'other'])->nullable();
            $table->text('reason_description')->nullable();
            $table->json('return_address')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->enum('refund_method', ['original', 'store_credit', 'bank_transfer', 'check'])->nullable();
            $table->text('admin_notes')->nullable();
            $table->enum('resolution_type', ['refund', 'replacement', 'repair', 'store_credit', 'exchange'])->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('customer_id');
            $table->index('sales_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rma_requests');
    }
};
