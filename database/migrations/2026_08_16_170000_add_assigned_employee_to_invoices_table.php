<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a direct sale name the rep who made it.
 *
 * `sales_orders` has carried `assigned_employee_id` for a while, so an order
 * can be credited to whoever sold it and the commission and performance reports
 * have something to group by. Invoices raised straight from the dashboard had
 * no such column, so a walk-in sale belonged to nobody — and the sales-by-rep
 * figures silently omitted every one of them.
 *
 * Nullable on purpose: not every sale has a rep behind it. A counter sale
 * rung up by an admin is a real sale, and forcing an employee onto it would
 * only produce a fictional attribution.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'assigned_employee_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('assigned_employee_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('employees')
                // The sale stays; it simply stops being attributed. Deleting a
                // leaver must not delete their invoices.
                ->nullOnDelete();

            $table->index(['assigned_employee_id', 'created_at'], 'invoices_employee_date_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'assigned_employee_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_employee_date_index');
            $table->dropConstrainedForeignId('assigned_employee_id');
        });
    }
};
