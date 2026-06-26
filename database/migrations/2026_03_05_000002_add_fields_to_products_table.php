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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('slug')->nullable()->index();

            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();

            $table->decimal('price', 10, 2)->nullable();

            $table->string('image_main')->nullable();
            $table->longText('image_gallery')->nullable();

            $table->string('brand')->nullable();
            $table->string('model')->nullable();

            $table->boolean('in_stock')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->boolean('is_active')->default(true);

            $table->index(['category_id', 'is_active', 'is_featured', 'in_stock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'is_active', 'is_featured', 'in_stock']);

            $table->dropConstrainedForeignId('category_id');

            $table->dropColumn([
                'name_ar',
                'name_en',
                'slug',
                'description_ar',
                'description_en',
                'price',
                'image_main',
                'image_gallery',
                'brand',
                'model',
                'in_stock',
                'is_featured',
                'views_count',
                'is_active',
            ]);
        });
    }
};
