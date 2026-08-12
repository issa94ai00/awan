<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The warehouses an order is deliberately routed through.
 *
 * An order already carried one `fulfillment_warehouse_id`, and the per-line
 * allocations could name any warehouse at all — so the sourcing screen offered
 * every active location for every line, whether or not anyone intended the order
 * to involve it. That made a split look like an accident rather than a decision,
 * and there was nowhere to record "these two branches are serving this order"
 * before deciding how much each one gives.
 *
 * This table is that decision. It is deliberately separate from the allocations:
 * a warehouse can be chosen and not yet given any quantity, which is exactly the
 * state an operator is in while distributing the lines.
 *
 * An order with no rows here keeps the old behaviour — every warehouse on offer
 * — so existing orders and every path that never touches this screen are
 * unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_routings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One row per warehouse per order: choosing the same place twice is
            // not a different decision.
            $table->unique(['sales_order_id', 'warehouse_id'], 'sales_order_routings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_routings');
    }
};
