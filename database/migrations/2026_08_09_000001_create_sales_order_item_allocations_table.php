<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order-item warehouse allocations.
 *
 * A single sales-order line may be fulfilled from several warehouses (the
 * quantity is split), so the plan lives in its own table instead of a
 * `warehouse_id` column on `sales_order_items`. The unique pair keeps the
 * plan deterministic: one allocation per (item, warehouse).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_item_id')
                ->constrained('sales_order_items')
                ->onDelete('cascade');
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->onDelete('restrict');
            $table->integer('quantity')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['sales_order_item_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_item_allocations');
    }
};
