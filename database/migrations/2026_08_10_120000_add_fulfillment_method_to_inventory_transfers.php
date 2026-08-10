<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a replenishment request gets from the main warehouse to the branch.
 *
 * A request used to have one implied answer — someone ships it — and the branch
 * simply waited. In practice a rep standing in the main warehouse collects the
 * goods themselves, and that is a different sequence: the stock does not leave
 * before it arrives, it leaves *as* it arrives. Recording which of the two was
 * agreed is what lets the stock movements follow the goods honestly.
 *
 * `status` also moves from an enum to a string. The lifecycle gained a stage
 * here, and an enum makes every future stage a schema migration on a table two
 * applications write to — with SQLite (the test database) rebuilding the whole
 * table each time. The values are constants on the model, which is where the
 * rest of this codebase keeps them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transfers', 'fulfillment_method')) {
                // Null until the source warehouse decides. The requester may
                // state a preference, but the warehouse holding the goods is
                // the one that knows whether it can spare a driver today.
                $table->string('fulfillment_method')->nullable()->after('status');
            }

            if (!Schema::hasColumn('inventory_transfers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('requested_at');
            }

            if (!Schema::hasColumn('inventory_transfers', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // Separate from the block above: changing a column type is a different
        // kind of operation, and on MySQL a failure here must not leave the new
        // columns half-added.
        Schema::table('inventory_transfers', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_transfers', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            foreach (['fulfillment_method', 'approved_at'] as $column) {
                if (Schema::hasColumn('inventory_transfers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
