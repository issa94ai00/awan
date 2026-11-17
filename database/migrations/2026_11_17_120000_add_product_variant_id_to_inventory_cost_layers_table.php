<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cost layers per variant (docs/fifo-variant-cost-layers-plan.md).
 *
 * Layers were keyed by product and warehouse only, so issuing one variant drew
 * whatever layer was oldest — a single piece could be costed at a whole box.
 * Null keeps meaning "not tied to a variant": layers from before this change,
 * and layers written by callers that do not know the variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
            $table->index(['product_id', 'warehouse_id', 'product_variant_id', 'remaining_quantity'], 'cost_layers_variant_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->dropIndex('cost_layers_variant_lookup');
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }
};
