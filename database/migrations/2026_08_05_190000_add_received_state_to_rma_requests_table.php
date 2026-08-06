<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the missing "received" step to the returns workflow.
 *
 * RmaController::receiveItems() already restocks the warehouse and writes stock
 * movements, but the request itself had nowhere to record that goods had
 * arrived: `status` had no `received` value and there was no timestamp. As a
 * result the receive step left no trace, the lifecycle stepper in the UI could
 * never advance past "approved" (it carried a workaround that inspected item
 * quantities), and receiving could be repeated indefinitely — each run
 * incrementing warehouse inventory again.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL enums cannot be extended through the Blueprint API, and the
        // column is an enum in the create migration. SQLite does not enforce
        // enums, so the new value needs no DDL there.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `rma_requests` MODIFY `status`
                 ENUM('pending','approved','received','rejected','completed','cancelled')
                 NOT NULL DEFAULT 'pending'"
            );
        }

        Schema::table('rma_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('rma_requests', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('rma_requests', 'received_by')) {
                $table->foreignId('received_by')->nullable()->after('approved_by')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rma_requests', function (Blueprint $table) {
            if (Schema::hasColumn('rma_requests', 'received_by')) {
                $table->dropConstrainedForeignId('received_by');
            }
            if (Schema::hasColumn('rma_requests', 'received_at')) {
                $table->dropColumn('received_at');
            }
        });

        // Any request sitting in the new state falls back to approved so the
        // narrowed enum stays valid.
        DB::table('rma_requests')->where('status', 'received')->update(['status' => 'approved']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `rma_requests` MODIFY `status`
                 ENUM('pending','approved','rejected','completed','cancelled')
                 NOT NULL DEFAULT 'pending'"
            );
        }
    }
};
