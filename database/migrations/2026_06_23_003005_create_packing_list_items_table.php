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
        Schema::create('packing_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_list_id')->constrained('packing_lists')->cascadeOnDelete();
            $table->foreignId('picking_list_item_id')->constrained('picking_list_items');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->integer('quantity');
            $table->string('package_number')->nullable();
            $table->json('dimensions')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->boolean('fragile')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['packing_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packing_list_items');
    }
};
