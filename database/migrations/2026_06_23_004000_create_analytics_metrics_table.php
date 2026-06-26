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
        Schema::create('analytics_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_key')->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->enum('category', ['sales', 'inventory', 'warehouse', 'financial', 'customer', 'operational']);
            $table->enum('data_type', ['number', 'percentage', 'currency', 'count', 'duration']);
            $table->enum('aggregation', ['sum', 'avg', 'count', 'min', 'max', 'last']);
            $table->string('unit')->nullable();
            $table->json('calculation_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_metrics');
    }
};
