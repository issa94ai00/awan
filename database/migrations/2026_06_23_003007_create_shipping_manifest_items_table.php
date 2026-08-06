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
        Schema::create('shipping_manifest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_manifest_id')->constrained('shipping_manifests')->cascadeOnDelete();
            $table->foreignId('packing_list_id')->constrained('packing_lists');
            $table->foreignId('sales_order_id')->constrained('sales_orders');
            $table->string('tracking_number')->nullable();
            $table->string('package_number');
            $table->decimal('weight', 10, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->enum('delivery_status', ['pending', 'in_transit', 'delivered', 'failed'])->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->string('signature')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['shipping_manifest_id']);
            $table->index(['sales_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_manifest_items');
    }
};
