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
        // Check if table exists from database.sql import
        if (Schema::hasTable('product_units')) {
            // Add missing columns if they don't exist
            Schema::table('product_units', function (Blueprint $table) {
                if (!Schema::hasColumn('product_units', 'barcode')) {
                    $table->string('barcode')->nullable()->after('name_ar');
                }
                if (!Schema::hasColumn('product_units', 'is_default')) {
                    $table->boolean('is_default')->default(false)->after('price_multiplier');
                }
                if (!Schema::hasColumn('product_units', 'created_at')) {
                    $table->timestamps();
                }
                
                // Add indexes if they don't exist
                $table->index('barcode');
            });
        } else {
            // Create table if it doesn't exist
            Schema::create('product_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
                $table->string('name');
                $table->string('name_ar')->nullable();
                $table->string('barcode')->nullable();
                $table->decimal('base_unit_multiplier', 10, 2)->default(1);
                $table->decimal('price_multiplier', 10, 2)->default(1);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index('product_id');
                $table->index('barcode');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
