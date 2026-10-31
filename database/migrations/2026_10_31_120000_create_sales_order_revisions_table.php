<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versions of a sales order.
 *
 * Once an order is confirmed, an edit no longer overwrites it in place: the
 * order as it stood is copied here (header and lines) and the order moves on
 * to the next revision number. The order row always holds the current
 * version; this table holds every one it replaced. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1)->after('order_number');
        });

        Schema::create('sales_order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();

            // The revision number this snapshot was, before it was replaced.
            $table->unsignedInteger('revision');
            $table->string('status', 30);

            $table->decimal('subtotal', 20, 5)->default(0);
            $table->decimal('discount', 20, 5)->default(0);
            $table->decimal('tax', 20, 5)->default(0);
            $table->decimal('shipping_cost', 20, 5)->default(0);
            $table->decimal('total', 20, 5)->default(0);

            // The full header and its lines as they stood.
            $table->json('snapshot');

            // Why it was replaced, and by whom.
            $table->text('note')->nullable();
            $table->foreignId('replaced_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['sales_order_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_revisions');

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('revision');
        });
    }
};
