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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->enum('type', ['sales', 'inventory', 'warehouse', 'financial', 'customer', 'custom']);
            $table->enum('format', ['table', 'chart', 'pivot', 'summary']);
            $table->json('query_config');
            $table->json('filter_config')->nullable();
            $table->json('column_config')->nullable();
            $table->json('chart_config')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_frequency')->nullable();
            $table->json('schedule_config')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'is_public']);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
