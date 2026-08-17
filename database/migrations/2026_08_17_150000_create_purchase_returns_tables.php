<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods going back to the supplier.
 *
 * The sales side has had returns since credit notes were built: stock comes
 * back, revenue is reduced through a contra account, the customer's receivable
 * drops. The purchase side had no equivalent at all — a delivery that was
 * wrong, damaged or short could be sent back in reality, and the system had no
 * document for it and no entry.
 *
 * What people did instead is the reason this matters: the only way to record it
 * was a manual stock adjustment, which books the goods out as **shrinkage**
 * against the inventory-difference account. So returning a faulty pump to the
 * supplier who sent it looked, in the income statement, exactly like losing it.
 * The debt to that supplier stayed on the books in full.
 *
 *   Dr  Accounts payable        what the supplier credits back
 *       Cr  Inventory — warehouse     what the goods cost us
 *       Cr  Input VAT                 tax reclaimed on the returned portion
 *
 * Returns are costed from the FIFO layers like any other issue, so what leaves
 * the books is what those units actually cost rather than today's price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();

            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            // The receipt being returned against, when there is one: a return
            // does not always trace to a single delivery.
            $table->foreignId('purchase_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();

            $table->date('return_date');
            $table->string('reason', 255)->nullable();
            // What the supplier credits back, which is not always what the goods
            // cost us — a restocking fee, or a price agreed on the phone.
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'return_date']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            // What the supplier is crediting per unit, and what the units
            // actually cost us — they are different questions and the gap
            // between them is a real gain or loss.
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
