<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_offer_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_offer_list_id')->constrained('price_offer_lists')->onDelete('cascade');
            // Each item references either a product row or a variant row, matching
            // the `p-{productId}` / `v-{variantId}` keys the frontend uses.
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_offer_list_items');
    }
};