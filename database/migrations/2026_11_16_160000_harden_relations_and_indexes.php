<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database design hardening (audit 2026-11-16).
 *
 * 1. Foreign keys that silently destroyed history when a master record was
 *    deleted now refuse instead (RESTRICT): deleting a supplier deleted its
 *    purchase orders, a customer its credit notes, an employee their
 *    commissions, a product or warehouse its stock movements and cost
 *    layers. An expense now outlives the invoice it was booked against.
 * 2. Natural keys the app already validates as unique get a UNIQUE index.
 * 3. Date/status columns that reports filter on get indexes.
 */
return new class extends Migration
{
    /** table => [column, referenced table, new rule, old rule] */
    private const RULES = [
        ['credit_notes', 'customer_id', 'customers', 'restrict', 'cascade'],
        ['purchase_orders', 'supplier_id', 'suppliers', 'restrict', 'cascade'],
        ['employee_commissions', 'employee_id', 'employees', 'restrict', 'cascade'],
        ['stock_movements', 'product_id', 'products', 'restrict', 'cascade'],
        ['stock_movements', 'warehouse_id', 'warehouses', 'restrict', 'set null'],
        ['inventory_cost_layers', 'product_id', 'products', 'restrict', 'cascade'],
        ['inventory_cost_layers', 'warehouse_id', 'warehouses', 'restrict', 'cascade'],
        ['expenses', 'invoice_id', 'invoices', 'set null', 'cascade'],
    ];

    private const UNIQUES = [
        ['products', 'slug', 'products_slug_unique'],
        ['categories', 'slug', 'categories_slug_unique'],
        ['customers', 'email', 'customers_email_unique'],
        ['employees', 'user_id', 'employees_user_id_unique'],
    ];

    private const INDEXES = [
        ['invoices', ['status', 'created_at'], 'invoices_status_created_index'],
        ['invoices', ['due_date'], 'invoices_due_date_index'],
        ['payments', ['payment_date'], 'payments_payment_date_index'],
        ['payments', ['status'], 'payments_status_index'],
        ['journal_entry_headers', ['entry_date'], 'journal_entry_headers_entry_date_index'],
        ['journal_entry_headers', ['status', 'entry_date'], 'journal_entry_headers_status_date_index'],
        ['sales_orders', ['status', 'order_date'], 'sales_orders_status_date_index'],
        ['purchase_orders', ['status', 'order_date'], 'purchase_orders_status_date_index'],
        ['purchase_receipts', ['receipt_date'], 'purchase_receipts_receipt_date_index'],
        ['supplier_payments', ['payment_date'], 'supplier_payments_payment_date_index'],
        ['quotes', ['status'], 'quotes_status_index'],
        ['stock_movements', ['product_id', 'warehouse_id', 'created_at'], 'stock_movements_product_wh_date_index'],
        ['stock_movements', ['movement_type', 'created_at'], 'stock_movements_type_date_index'],
    ];

    public function up(): void
    {
        $this->applyRules(3);

        foreach (self::UNIQUES as [$table, $column, $name]) {
            if (! Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->unique($column, $name));
            }
        }

        foreach (self::INDEXES as [$table, $columns, $name]) {
            if (! Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as [$table, , $name]) {
            if (Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }

        foreach (self::UNIQUES as [$table, , $name]) {
            if (Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($name));
            }
        }

        $this->applyRules(4);
    }

    /** Re-creates each foreign key with the rule at $ruleIndex. MySQL only. */
    private function applyRules(int $ruleIndex): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::RULES as $rule) {
            [$table, $column, $references] = $rule;
            $onDelete = $rule[$ruleIndex];

            Schema::table($table, function (Blueprint $t) use ($table, $column, $references, $onDelete) {
                $t->dropForeign("{$table}_{$column}_foreign");
                $t->foreign($column, "{$table}_{$column}_foreign")
                    ->references('id')->on($references)->onDelete($onDelete);
            });
        }
    }
};
