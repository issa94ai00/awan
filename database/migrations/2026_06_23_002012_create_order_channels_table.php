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
        Schema::create('order_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('code')->unique();
            $table->enum('type', ['online', 'retail', 'b2b', 'marketplace', 'api']);
            $table->string('integration_type')->nullable(); // shopify, bigcommerce, custom
            $table->json('config')->nullable(); // API keys, webhooks, etc.
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_channels');
    }
};
