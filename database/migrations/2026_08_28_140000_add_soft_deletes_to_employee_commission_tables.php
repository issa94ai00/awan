<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_commissions', function (Blueprint $table) {
            // Every list/print query filters on `deleted_at IS NULL` (or, for
            // the trash view, `IS NOT NULL`) scoped to one employee — this
            // index covers exactly that lookup instead of falling back to a
            // full scan once the table accumulates soft-deleted rows. Added
            // *before* the drop below so employee_id keeps a leading index
            // to back its foreign key once employee_id_month_unique is gone
            // (MySQL refuses to drop the last index covering an FK column).
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();
            $table->index(['employee_id', 'deleted_at']);

            // The (employee_id, month) unique index would collide with a
            // soft-deleted row when the same month is re-created — MySQL has
            // no partial/filtered unique index, so uniqueness among *active*
            // rows is enforced in the controller instead (see
            // EmployeeCommissionController::destroy()/store()).
            $table->dropUnique(['employee_id', 'month']);
        });

        Schema::table('employee_commission_withdrawals', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['employee_commission_id', 'deleted_at'], 'ecw_commission_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('employee_commission_withdrawals', function (Blueprint $table) {
            $table->dropIndex('ecw_commission_deleted_at_index');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });

        Schema::table('employee_commissions', function (Blueprint $table) {
            // Recreate the unique index first so employee_id never loses its
            // last covering index for the foreign key mid-rollback.
            $table->unique(['employee_id', 'month']);
            $table->dropIndex(['employee_id', 'deleted_at']);
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });
    }
};
