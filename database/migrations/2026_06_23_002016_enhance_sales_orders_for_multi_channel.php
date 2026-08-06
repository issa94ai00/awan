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
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'channel_id')) {
                $table->foreignId('channel_id')->nullable()->constrained('order_channels')->after('customer_id');
            }
            if (!Schema::hasColumn('sales_orders', 'external_order_id')) {
                $table->string('external_order_id')->nullable()->after('channel_id');
            }
            if (!Schema::hasColumn('sales_orders', 'contract_id')) {
                $table->foreignId('contract_id')->nullable()->constrained('sales_contracts')->after('external_order_id');
            }
            if (!Schema::hasColumn('sales_orders', 'fulfillment_type')) {
                $table->enum('fulfillment_type', ['ship', 'pickup', 'delivery'])->default('ship')->after('contract_id');
            }
            if (!Schema::hasColumn('sales_orders', 'fulfillment_warehouse_id')) {
                $table->foreignId('fulfillment_warehouse_id')->nullable()->constrained('warehouses')->after('fulfillment_type');
            }
            if (!Schema::hasColumn('sales_orders', 'actual_delivery_date')) {
                $table->timestamp('actual_delivery_date')->nullable()->after('expected_delivery');
            }
            if (!Schema::hasColumn('sales_orders', 'billing_address')) {
                $table->json('billing_address')->nullable()->after('shipping_address');
            }
            if (!Schema::hasColumn('sales_orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('billing_address');
            }
            if (!Schema::hasColumn('sales_orders', 'carrier')) {
                $table->string('carrier')->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('sales_orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)->default(0)->after('carrier');
            }
            if (!Schema::hasColumn('sales_orders', 'coupon_code')) {
                $table->string('coupon_code')->nullable()->after('discount');
            }
            if (!Schema::hasColumn('sales_orders', 'customer_notes')) {
                $table->text('customer_notes')->nullable()->after('coupon_code');
            }
            if (!Schema::hasColumn('sales_orders', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('customer_notes');
            }
            if (!Schema::hasColumn('sales_orders', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('internal_notes');
            }
            if (!Schema::hasColumn('sales_orders', 'sync_status')) {
                $table->string('sync_status')->nullable()->after('synced_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $columnsToDrop = [
                'channel_id', 'external_order_id', 'contract_id', 'fulfillment_type',
                'fulfillment_warehouse_id', 'actual_delivery_date',
                'billing_address', 'tracking_number', 'carrier',
                'shipping_cost', 'coupon_code',
                'customer_notes', 'internal_notes', 'synced_at', 'sync_status'
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('sales_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
