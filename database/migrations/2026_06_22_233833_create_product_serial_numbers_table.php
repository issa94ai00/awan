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
        Schema::create('product_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('batch_id')->nullable()->constrained('product_batches');
            $table->string('serial_number')->unique();
            $table->enum('status', ['in_stock', 'reserved', 'sold', 'damaged', 'lost', 'quarantined'])->default('in_stock');
            $table->foreignId('sale_order_id')->nullable()->constrained('sales_orders');
            $table->foreignId('sale_order_item_id')->nullable()->constrained('sales_order_items');
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['serial_number']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_serial_numbers');
    }
};
