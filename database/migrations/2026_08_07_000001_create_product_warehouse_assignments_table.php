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
        Schema::create('product_warehouse_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            
            // Future-dated assignment support
            $table->date('effective_date')->default(now());
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Per-warehouse planning data
            $table->enum('replenishment_method', ['purchase', 'manufacture', 'internal_distribution', 'warehouse_transfer'])->default('purchase');
            $table->enum('planning_method', ['rop', 'mrp'])->default('rop');
            $table->integer('min_stock_level')->default(0);
            $table->integer('max_stock_level')->default(0);
            $table->integer('safety_stock')->default(0);
            
            // Supplier and lead time (warehouse-specific)
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->integer('lead_time_days')->default(7);
            
            // Bin management
            $table->foreignId('primary_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->enum('putaway_strategy', ['fifo', 'fefo', 'similarity', 'weight_based', 'volume_based'])->default('fifo');
            
            // Auto-reorder
            $table->boolean('auto_reorder_enabled')->default(false);
            
            // Notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Unique constraint to prevent duplicate assignments
            $table->unique(['product_id', 'warehouse_id', 'effective_date'], 'unique_warehouse_assignment');
            
            // Indexes for common queries
            $table->index(['warehouse_id', 'is_active']);
            $table->index(['effective_date', 'expiry_date']);
            $table->index(['product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_warehouse_assignments');
    }
};
