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
        Schema::create('analytics_data_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained('analytics_metrics')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('channel_id')->nullable()->constrained('order_channels');
            $table->date('recorded_date');
            $table->decimal('value', 20, 4);
            $table->json('dimensions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['metric_id', 'recorded_date']);
            $table->index(['warehouse_id', 'recorded_date']);
            $table->index(['channel_id', 'recorded_date']);
            $table->index('recorded_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_data_points');
    }
};
