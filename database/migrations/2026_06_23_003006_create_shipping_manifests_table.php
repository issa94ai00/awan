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
        Schema::create('shipping_manifests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('manifest_number')->unique();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->string('carrier_name')->nullable();
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'cancelled'])->default('pending');
            $table->date('shipping_date');
            $table->date('estimated_delivery')->nullable();
            $table->date('actual_delivery')->nullable();
            $table->integer('total_packages')->default(0);
            $table->decimal('total_weight', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->string('tracking_number')->nullable();
            $table->json('route')->nullable(); // Shipping route details
            $table->foreignId('driver_id')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['warehouse_id', 'status']);
            $table->index(['shipping_date']);
            $table->index(['carrier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_manifests');
    }
};
