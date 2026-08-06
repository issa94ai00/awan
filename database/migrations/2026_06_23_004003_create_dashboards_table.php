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
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->enum('type', ['executive', 'sales', 'inventory', 'warehouse', 'financial', 'custom']);
            $table->json('layout_config')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_default')->default(false);
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
        Schema::dropIfExists('dashboards');
    }
};
