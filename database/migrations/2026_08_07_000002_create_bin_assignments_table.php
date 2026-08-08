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
        Schema::create('bin_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_warehouse_assignment_id')->constrained('product_warehouse_assignments')->cascadeOnDelete();
            $table->foreignId('bin_id')->constrained('warehouse_bins')->cascadeOnDelete();
            
            // Primary vs secondary bin designation
            $table->boolean('is_primary')->default(false);
            
            // Priority order for putaway/picking (lower = higher priority)
            $table->integer('priority_order')->default(0);
            
            // Capacity allocation percentage
            $table->decimal('capacity_percentage', 5, 2)->default(0);
            
            $table->timestamps();
            
            // Ensure only one primary bin per assignment
            $table->unique(['product_warehouse_assignment_id', 'is_primary'], 'unique_primary_bin');
            
            // Indexes
            $table->index(['bin_id', 'is_primary'], 'idx_bin_primary');
            $table->index(['product_warehouse_assignment_id', 'priority_order'], 'idx_pwa_priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bin_assignments');
    }
};
