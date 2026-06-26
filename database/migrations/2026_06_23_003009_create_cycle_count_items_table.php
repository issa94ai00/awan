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
        Schema::create('cycle_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_count_id')->constrained('cycle_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('bin_id')->nullable()->constrained('warehouse_bins');
            $table->integer('expected_quantity');
            $table->integer('counted_quantity');
            $table->integer('variance')->default(0);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('variance_value', 10, 2)->default(0);
            $table->enum('variance_reason', ['theft', 'damage', 'data_entry', 'unknown'])->nullable();
            $table->boolean('verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['cycle_count_id']);
            $table->index(['product_id']);
            $table->index(['bin_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cycle_count_items');
    }
};
