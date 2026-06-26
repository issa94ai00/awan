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
        // 1. product_variants
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('material')->nullable();
            $table->timestamps();
        });

        // 2. warehouse_inventory
        Schema::create('warehouse_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('reorder_point')->default(10);
            $table->integer('safety_stock')->default(5);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id', 'product_variant_id'], 'wh_prod_var_unique');
        });

        // 3. landed_costs
        Schema::create('landed_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
            $table->decimal('shipping_charges', 10, 2)->default(0);
            $table->decimal('customs_duties', 10, 2)->default(0);
            $table->decimal('insurance_cost', 10, 2)->default(0);
            $table->decimal('other_charges', 10, 2)->default(0);
            $table->string('allocation_method')->default('value');
            $table->timestamps();
        });

        // 4. integration_settings
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name');
            $table->string('api_domain');
            $table->text('access_token');
            $table->boolean('sync_stock')->default(true);
            $table->boolean('sync_orders')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        // 5. rma_requests
        Schema::create('rma_requests', function (Blueprint $table) {
            $table->id();
            $table->string('rma_number')->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('status')->default('requested');
            $table->text('reason')->nullable();
            $table->string('resolution_type');
            $table->timestamps();
        });

        // 6. warehouse_bins
        Schema::create('warehouse_bins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('bin_code')->unique();
            $table->string('zone')->nullable();
            $table->string('rack')->nullable();
            $table->string('shelf')->nullable();
            $table->integer('max_weight')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_bins');
        Schema::dropIfExists('rma_requests');
        Schema::dropIfExists('integration_settings');
        Schema::dropIfExists('landed_costs');
        Schema::dropIfExists('warehouse_inventory');
        Schema::dropIfExists('product_variants');
    }
};
