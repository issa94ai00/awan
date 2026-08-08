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
        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            
            // Quantity of component required for one unit of parent product
            $table->integer('quantity_required')->default(1);
            
            // Whether this component is optional
            $table->boolean('is_optional')->default(false);
            
            // Notes for assembly instructions
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Prevent duplicate component entries
            $table->unique(['parent_product_id', 'component_product_id'], 'unique_product_component');
            
            // Indexes
            $table->index('parent_product_id');
            $table->index('component_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_components');
    }
};
