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
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('action_type')->default('task');
            $table->json('action_config')->nullable();
            $table->integer('order')->default(0);
            $table->string('condition_type')->nullable();
            $table->json('condition_config')->nullable();
            $table->boolean('is_parallel')->default(false);
            $table->boolean('is_required')->default(true);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('estimated_duration')->nullable();
            $table->timestamps();

            $table->index('workflow_id');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
