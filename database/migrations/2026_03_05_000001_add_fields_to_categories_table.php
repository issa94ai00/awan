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
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('icon')->default('fa-box');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('product_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'sort_order']);

            $table->dropColumn([
                'name_ar',
                'name_en',
                'icon',
                'description',
                'image',
                'product_count',
                'sort_order',
                'is_active',
            ]);
        });
    }
};
