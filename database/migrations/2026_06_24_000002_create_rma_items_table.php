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
        Schema::create('rma_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_request_id')->constrained('rma_requests')->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->nullable()->constrained('sales_order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('quantity_requested');
            $table->integer('quantity_received')->default(0);
            $table->enum('condition', ['new', 'used', 'damaged', 'missing'])->default('new');
            $table->enum('resolution', ['refund', 'exchange', 'repair', 'discard'])->nullable();
            $table->foreignId('exchange_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('exchange_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('rma_request_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rma_items');
    }
};
