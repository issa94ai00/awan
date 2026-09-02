<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens every genuinely monetary decimal column from 2 (or, for a couple of
 * cost columns, 4) decimal places to 5.
 *
 * decimal(x,2) does not round for display — it rounds at rest. A unit cost
 * derived from a bulk purchase (total cost / quantity) routinely lands on a
 * fraction finer than a cent, and every one of those got truncated the
 * moment it was saved, before any report or margin calculation ever saw the
 * real figure. This does not touch percentages, rates, multipliers, physical
 * dimensions/weights, or geo-coordinates — those were never money and 5
 * decimal places would be meaningless on them.
 *
 * Each column keeps its original integer-digit capacity (so a total that
 * already fit still fits) and gains 3 more decimal digits of scale. Nullability
 * and defaults are preserved exactly as they were.
 *
 * Generated from information_schema against the live schema rather than
 * written by hand, because 122 columns across 45 tables is not something to
 * transcribe correctly by eye twice (once for up, once for down).
 *
 * The `ALTER TABLE ... MODIFY COLUMN` statements are MySQL syntax, which is
 * what production runs on. The test suite runs its own schema on SQLite,
 * which does not understand MODIFY COLUMN and, being dynamically typed,
 * does not enforce decimal precision on a column in the first place — a
 * value with more than 2 decimal places round-trips through it untouched
 * regardless of what the column declares. So there is nothing this
 * migration needs to do there; it only runs its statements on MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::statements() as [$up, $down]) {
            DB::statement($up);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::statements() as [$up, $down]) {
            DB::statement($down);
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}> [up statement, down statement] pairs
     */
    private static function statements(): array
    {
        $up = [
            "ALTER TABLE `bank_reconciliations` MODIFY COLUMN `statement_balance` DECIMAL(18,5) NOT NULL;",
            "ALTER TABLE `budget_lines` MODIFY COLUMN `amount` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `cart_items` MODIFY COLUMN `price` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_note_items` MODIFY COLUMN `total` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_note_items` MODIFY COLUMN `unit_price` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `applied_to_invoice` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `refunded_amount` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `store_credit_amount` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `subtotal` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `tax` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `total` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `customers` MODIFY COLUMN `balance` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `customers` MODIFY COLUMN `credit_limit` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `customers` MODIFY COLUMN `total_purchases` DECIMAL(17,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `cycle_count_items` MODIFY COLUMN `unit_cost` DECIMAL(13,5) NULL DEFAULT NULL;",
            "ALTER TABLE `cycle_count_items` MODIFY COLUMN `variance_value` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `cycle_counts` MODIFY COLUMN `variance_value` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employee_commission_withdrawals` MODIFY COLUMN `amount` DECIMAL(17,5) NOT NULL;",
            "ALTER TABLE `employee_commission_withdrawals` MODIFY COLUMN `base_amount` DECIMAL(17,5) NOT NULL;",
            "ALTER TABLE `employee_commissions` MODIFY COLUMN `extra_expenses` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employee_commissions` MODIFY COLUMN `monthly_target` DECIMAL(15,5) NULL DEFAULT NULL;",
            "ALTER TABLE `employee_commissions` MODIFY COLUMN `total_sales` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employee_commissions` MODIFY COLUMN `withdrawals` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employees` MODIFY COLUMN `bonus` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employees` MODIFY COLUMN `end_of_service_accrued` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employees` MODIFY COLUMN `monthly_sales_target` DECIMAL(15,5) NULL DEFAULT NULL;",
            "ALTER TABLE `employees` MODIFY COLUMN `salary` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `expenses` MODIFY COLUMN `amount` DECIMAL(13,5) NOT NULL;",
            "ALTER TABLE `fixed_assets` MODIFY COLUMN `accumulated_depreciation` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `fixed_assets` MODIFY COLUMN `cost` DECIMAL(18,5) NOT NULL;",
            "ALTER TABLE `fixed_assets` MODIFY COLUMN `disposal_proceeds` DECIMAL(18,5) NULL DEFAULT NULL;",
            "ALTER TABLE `fixed_assets` MODIFY COLUMN `salvage_value` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `inventory_cost_layers` MODIFY COLUMN `unit_cost` DECIMAL(13,5) NOT NULL;",
            "ALTER TABLE `inventory_transfer_items` MODIFY COLUMN `unit_cost` DECIMAL(13,5) NOT NULL;",
            "ALTER TABLE `invoice_items` MODIFY COLUMN `discount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoice_items` MODIFY COLUMN `tax_amount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoice_items` MODIFY COLUMN `total_price` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoice_items` MODIFY COLUMN `unit_price` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `additional_charges` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `discount` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `due_amount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `paid_amount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `subtotal` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `tax` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `total` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `journal_entry_headers` MODIFY COLUMN `total_credit` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `journal_entry_headers` MODIFY COLUMN `total_debit` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `journal_entry_lines` MODIFY COLUMN `credit` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `journal_entry_lines` MODIFY COLUMN `debit` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `landed_costs` MODIFY COLUMN `customs_duties` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `landed_costs` MODIFY COLUMN `insurance_cost` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `landed_costs` MODIFY COLUMN `other_charges` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `landed_costs` MODIFY COLUMN `shipping_charges` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `ledger_accounts` MODIFY COLUMN `balance` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `ledger_accounts` MODIFY COLUMN `opening_balance` DECIMAL(17,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `order_items` MODIFY COLUMN `price` DECIMAL(13,5) NOT NULL;",
            "ALTER TABLE `order_items` MODIFY COLUMN `price_after_discount` DECIMAL(13,5) NULL DEFAULT NULL;",
            "ALTER TABLE `orders` MODIFY COLUMN `shipping_cost` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `orders` MODIFY COLUMN `subtotal` DECIMAL(13,5) NOT NULL;",
            "ALTER TABLE `orders` MODIFY COLUMN `tax` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `orders` MODIFY COLUMN `total` DECIMAL(13,5) NOT NULL;",
            "ALTER TABLE `payments` MODIFY COLUMN `amount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payments` MODIFY COLUMN `tendered_amount` DECIMAL(17,5) NULL DEFAULT NULL;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `basic_salary` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `bonuses` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `deductions` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `net_salary` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `overtime_pay` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `overtime_rate` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `product_batches` MODIFY COLUMN `unit_cost` DECIMAL(13,5) NOT NULL;",
            "ALTER TABLE `product_variants` MODIFY COLUMN `cost_price` DECIMAL(13,5) NULL DEFAULT NULL;",
            "ALTER TABLE `product_variants` MODIFY COLUMN `price` DECIMAL(13,5) NOT NULL;",
            "ALTER TABLE `production_orders` MODIFY COLUMN `cost` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `products` MODIFY COLUMN `cost_price` DECIMAL(13,5) NULL DEFAULT NULL;",
            "ALTER TABLE `products` MODIFY COLUMN `price` DECIMAL(13,5) NULL DEFAULT NULL;",
            "ALTER TABLE `purchase_order_items` MODIFY COLUMN `sale_price` DECIMAL(18,5) NULL DEFAULT NULL;",
            "ALTER TABLE `purchase_order_items` MODIFY COLUMN `total_price` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_order_items` MODIFY COLUMN `unit_price` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `discount` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `due_amount` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `paid_amount` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `subtotal` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `tax` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `total` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_receipt_items` MODIFY COLUMN `sale_price` DECIMAL(18,5) NULL DEFAULT NULL;",
            "ALTER TABLE `purchase_receipt_items` MODIFY COLUMN `total` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_receipt_items` MODIFY COLUMN `unit_price` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_receipts` MODIFY COLUMN `tax_amount` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_return_items` MODIFY COLUMN `unit_cost` DECIMAL(16,5) NOT NULL DEFAULT 0.0000;",
            "ALTER TABLE `purchase_return_items` MODIFY COLUMN `unit_price` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_returns` MODIFY COLUMN `credit_amount` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_returns` MODIFY COLUMN `tax_amount` DECIMAL(18,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quote_items` MODIFY COLUMN `discount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quote_items` MODIFY COLUMN `tax` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quote_items` MODIFY COLUMN `total` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quote_items` MODIFY COLUMN `unit_price` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quotes` MODIFY COLUMN `discount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quotes` MODIFY COLUMN `subtotal` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quotes` MODIFY COLUMN `tax` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quotes` MODIFY COLUMN `total` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `rma_items` MODIFY COLUMN `refund_amount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `rma_requests` MODIFY COLUMN `refund_amount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_contracts` MODIFY COLUMN `total_value` DECIMAL(18,5) NOT NULL;",
            "ALTER TABLE `sales_order_items` MODIFY COLUMN `discount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_order_items` MODIFY COLUMN `tax` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_order_items` MODIFY COLUMN `total` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_order_items` MODIFY COLUMN `unit_price` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `discount` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `due_amount` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `paid_amount` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `shipping_cost` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `subtotal` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `tax` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `total` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `shipping_manifests` MODIFY COLUMN `shipping_cost` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `stock_movements` MODIFY COLUMN `total_cost` DECIMAL(17,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `stock_movements` MODIFY COLUMN `unit_cost` DECIMAL(15,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `supplier_payments` MODIFY COLUMN `amount` DECIMAL(18,5) NOT NULL;",
            "ALTER TABLE `suppliers` MODIFY COLUMN `balance` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `suppliers` MODIFY COLUMN `credit_limit` DECIMAL(13,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `suppliers` MODIFY COLUMN `total_purchases` DECIMAL(17,5) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `wallet_transactions` MODIFY COLUMN `amount` DECIMAL(13,5) NOT NULL;",
        ];

        $down = [
            "ALTER TABLE `bank_reconciliations` MODIFY COLUMN `statement_balance` DECIMAL(15,2) NOT NULL;",
            "ALTER TABLE `budget_lines` MODIFY COLUMN `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `cart_items` MODIFY COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_note_items` MODIFY COLUMN `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_note_items` MODIFY COLUMN `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `applied_to_invoice` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `refunded_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `store_credit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `tax` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `credit_notes` MODIFY COLUMN `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `customers` MODIFY COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `customers` MODIFY COLUMN `credit_limit` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `customers` MODIFY COLUMN `total_purchases` DECIMAL(14,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `cycle_count_items` MODIFY COLUMN `unit_cost` DECIMAL(10,2) NULL DEFAULT NULL;",
            "ALTER TABLE `cycle_count_items` MODIFY COLUMN `variance_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `cycle_counts` MODIFY COLUMN `variance_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employee_commission_withdrawals` MODIFY COLUMN `amount` DECIMAL(14,2) NOT NULL;",
            "ALTER TABLE `employee_commission_withdrawals` MODIFY COLUMN `base_amount` DECIMAL(14,2) NOT NULL;",
            "ALTER TABLE `employee_commissions` MODIFY COLUMN `extra_expenses` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employee_commissions` MODIFY COLUMN `monthly_target` DECIMAL(12,2) NULL DEFAULT NULL;",
            "ALTER TABLE `employee_commissions` MODIFY COLUMN `total_sales` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employee_commissions` MODIFY COLUMN `withdrawals` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employees` MODIFY COLUMN `bonus` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employees` MODIFY COLUMN `end_of_service_accrued` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `employees` MODIFY COLUMN `monthly_sales_target` DECIMAL(12,2) NULL DEFAULT NULL;",
            "ALTER TABLE `employees` MODIFY COLUMN `salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `expenses` MODIFY COLUMN `amount` DECIMAL(10,2) NOT NULL;",
            "ALTER TABLE `fixed_assets` MODIFY COLUMN `accumulated_depreciation` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `fixed_assets` MODIFY COLUMN `cost` DECIMAL(15,2) NOT NULL;",
            "ALTER TABLE `fixed_assets` MODIFY COLUMN `disposal_proceeds` DECIMAL(15,2) NULL DEFAULT NULL;",
            "ALTER TABLE `fixed_assets` MODIFY COLUMN `salvage_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `inventory_cost_layers` MODIFY COLUMN `unit_cost` DECIMAL(12,4) NOT NULL;",
            "ALTER TABLE `inventory_transfer_items` MODIFY COLUMN `unit_cost` DECIMAL(10,2) NOT NULL;",
            "ALTER TABLE `invoice_items` MODIFY COLUMN `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoice_items` MODIFY COLUMN `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoice_items` MODIFY COLUMN `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoice_items` MODIFY COLUMN `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `additional_charges` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `due_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `tax` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `invoices` MODIFY COLUMN `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `journal_entry_headers` MODIFY COLUMN `total_credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `journal_entry_headers` MODIFY COLUMN `total_debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `journal_entry_lines` MODIFY COLUMN `credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `journal_entry_lines` MODIFY COLUMN `debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `landed_costs` MODIFY COLUMN `customs_duties` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `landed_costs` MODIFY COLUMN `insurance_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `landed_costs` MODIFY COLUMN `other_charges` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `landed_costs` MODIFY COLUMN `shipping_charges` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `ledger_accounts` MODIFY COLUMN `balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `ledger_accounts` MODIFY COLUMN `opening_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `order_items` MODIFY COLUMN `price` DECIMAL(10,2) NOT NULL;",
            "ALTER TABLE `order_items` MODIFY COLUMN `price_after_discount` DECIMAL(10,2) NULL DEFAULT NULL;",
            "ALTER TABLE `orders` MODIFY COLUMN `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `orders` MODIFY COLUMN `subtotal` DECIMAL(10,2) NOT NULL;",
            "ALTER TABLE `orders` MODIFY COLUMN `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `orders` MODIFY COLUMN `total` DECIMAL(10,2) NOT NULL;",
            "ALTER TABLE `payments` MODIFY COLUMN `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payments` MODIFY COLUMN `tendered_amount` DECIMAL(14,2) NULL DEFAULT NULL;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `basic_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `bonuses` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `deductions` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `net_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `overtime_pay` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `payrolls` MODIFY COLUMN `overtime_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `product_batches` MODIFY COLUMN `unit_cost` DECIMAL(10,2) NOT NULL;",
            "ALTER TABLE `product_variants` MODIFY COLUMN `cost_price` DECIMAL(10,2) NULL DEFAULT NULL;",
            "ALTER TABLE `product_variants` MODIFY COLUMN `price` DECIMAL(10,2) NOT NULL;",
            "ALTER TABLE `production_orders` MODIFY COLUMN `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `products` MODIFY COLUMN `cost_price` DECIMAL(10,2) NULL DEFAULT NULL;",
            "ALTER TABLE `products` MODIFY COLUMN `price` DECIMAL(10,2) NULL DEFAULT NULL;",
            "ALTER TABLE `purchase_order_items` MODIFY COLUMN `sale_price` DECIMAL(15,2) NULL DEFAULT NULL;",
            "ALTER TABLE `purchase_order_items` MODIFY COLUMN `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_order_items` MODIFY COLUMN `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `tax` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_orders` MODIFY COLUMN `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_receipt_items` MODIFY COLUMN `sale_price` DECIMAL(15,2) NULL DEFAULT NULL;",
            "ALTER TABLE `purchase_receipt_items` MODIFY COLUMN `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_receipt_items` MODIFY COLUMN `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_receipts` MODIFY COLUMN `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_return_items` MODIFY COLUMN `unit_cost` DECIMAL(15,4) NOT NULL DEFAULT 0.0000;",
            "ALTER TABLE `purchase_return_items` MODIFY COLUMN `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_returns` MODIFY COLUMN `credit_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `purchase_returns` MODIFY COLUMN `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quote_items` MODIFY COLUMN `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quote_items` MODIFY COLUMN `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quote_items` MODIFY COLUMN `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quote_items` MODIFY COLUMN `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quotes` MODIFY COLUMN `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quotes` MODIFY COLUMN `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quotes` MODIFY COLUMN `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `quotes` MODIFY COLUMN `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `rma_items` MODIFY COLUMN `refund_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `rma_requests` MODIFY COLUMN `refund_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_contracts` MODIFY COLUMN `total_value` DECIMAL(15,2) NOT NULL;",
            "ALTER TABLE `sales_order_items` MODIFY COLUMN `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_order_items` MODIFY COLUMN `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_order_items` MODIFY COLUMN `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_order_items` MODIFY COLUMN `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `sales_orders` MODIFY COLUMN `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `shipping_manifests` MODIFY COLUMN `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `stock_movements` MODIFY COLUMN `total_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `stock_movements` MODIFY COLUMN `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `supplier_payments` MODIFY COLUMN `amount` DECIMAL(15,2) NOT NULL;",
            "ALTER TABLE `suppliers` MODIFY COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `suppliers` MODIFY COLUMN `credit_limit` DECIMAL(10,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `suppliers` MODIFY COLUMN `total_purchases` DECIMAL(14,2) NOT NULL DEFAULT 0.00;",
            "ALTER TABLE `wallet_transactions` MODIFY COLUMN `amount` DECIMAL(10,2) NOT NULL;",
        ];

        return array_map(null, $up, $down);
    }
};
