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
        Schema::create('product_assembly_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('assembly_area_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->string('assembly_number')->unique();
            $table->integer('quantity_to_assemble');
            $table->integer('quantity_assembled')->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['warehouse_id', 'status'], 'idx_warehouse_status');
            $table->index(['parent_product_id', 'status'], 'idx_product_status');
            $table->index('created_at', 'idx_created_at');
        });

        Schema::create('product_assembly_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assembly_order_id')->constrained('product_assembly_orders')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity_required');
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_consumed')->default(0);
            $table->foreignId('bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->string('batch_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['assembly_order_id', 'component_product_id'], 'idx_assembly_component');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_assembly_order_items');
        Schema::dropIfExists('product_assembly_orders');
    }
};
