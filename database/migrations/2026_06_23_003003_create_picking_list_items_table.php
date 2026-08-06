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
        Schema::create('picking_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picking_list_id')->constrained('picking_lists')->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->nullable()->constrained('sales_order_items');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('bin_id')->nullable()->constrained('warehouse_bins');
            $table->integer('quantity_to_pick');
            $table->integer('quantity_picked')->default(0);
            $table->enum('status', ['pending', 'picked', 'short', 'cancelled'])->default('pending');
            $table->string('barcode')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('picked_at')->nullable();
            $table->integer('sort_order')->default(0); // For optimized route
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['picking_list_id', 'status']);
            $table->index(['bin_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('picking_list_items');
    }
};
