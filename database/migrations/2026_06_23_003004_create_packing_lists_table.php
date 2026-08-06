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
        Schema::create('packing_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('picking_list_id')->constrained('picking_lists');
            $table->foreignId('sales_order_id')->constrained('sales_orders');
            $table->string('list_number')->unique();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('packer_id')->nullable()->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_packages')->default(0);
            $table->decimal('total_weight', 10, 2)->default(0);
            $table->json('dimensions')->nullable(); // Total package dimensions
            $table->string('box_type')->nullable();
            $table->text('packing_instructions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['warehouse_id', 'status']);
            $table->index(['packer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packing_lists');
    }
};
