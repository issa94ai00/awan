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
        Schema::create('reorder_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->integer('current_quantity');
            $table->integer('reorder_point');
            $table->integer('safety_stock');
            $table->integer('suggested_order_quantity');
            $table->enum('severity', ['low', 'medium', 'critical'])->default('medium');
            $table->enum('status', ['pending', 'ordered', 'resolved', 'dismissed'])->default('pending');
            $table->timestamp('alerted_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['status']);
            $table->index(['severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reorder_alerts');
    }
};
