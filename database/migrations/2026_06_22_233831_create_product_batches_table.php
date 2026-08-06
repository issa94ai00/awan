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
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('batch_number')->unique();
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date');
            $table->integer('quantity');
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_available')->virtualAs('quantity - quantity_reserved');
            $table->decimal('unit_cost', 10, 2);
            $table->enum('status', ['available', 'reserved', 'expired', 'quarantined'])->default('available');
            $table->timestamps();
            
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
