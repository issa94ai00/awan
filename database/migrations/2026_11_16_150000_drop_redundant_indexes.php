<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Each of these single-column indexes is the leftmost prefix of another
 * index on the same table, so it only costs writes. The covering index
 * still serves every foreign key that relied on it.
 */
return new class extends Migration
{
    private const DROPS = [
        'customer_employee' => ['customer_employee_customer_index', 'customer_employee_employee_index'],
        'product_components' => ['product_components_parent_product_id_index'],
        'product_serial_numbers' => ['product_serial_numbers_serial_number_index'],
    ];

    public function up(): void
    {
        foreach (self::DROPS as $table => $indexes) {
            foreach ($indexes as $index) {
                if (Schema::hasIndex($table, $index)) {
                    Schema::table($table, fn ($t) => $t->dropIndex($index));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('customer_employee', function ($t) {
            $t->index('customer_id', 'customer_employee_customer_index');
            $t->index('employee_id', 'customer_employee_employee_index');
        });
        Schema::table('product_components', fn ($t) => $t->index('parent_product_id'));
        Schema::table('product_serial_numbers', fn ($t) => $t->index('serial_number'));
    }
};
