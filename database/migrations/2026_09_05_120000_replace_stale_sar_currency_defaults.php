<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The four financial tables carried `currency DEFAULT 'SAR'`.
 *
 * That literal came from the application's original Saudi context and long
 * outlived it: the configured base currency is read from the `currencies`
 * table, and on books based in anything else every document created by a path
 * that did not set `currency` itself was stamped with a currency nobody had
 * configured. Nothing converts at posting time, so the amounts reached the
 * ledger at face value — right figures, wrong label — and the accounting
 * health check duly reported every document in the system as foreign.
 *
 * App\Models\Concerns\RecordsInBaseCurrency is the real authority now: it
 * fills the field from base_currency_code() on the way in, so the value
 * follows configuration instead of the schema. This migration only stops the
 * database from contradicting it when a row is inserted by something other
 * than a model — a seeder, a raw statement, an import.
 *
 * Existing rows are deliberately left alone. Relabelling money is a decision
 * about what those amounts actually are, not a schema concern.
 */
return new class extends Migration
{
    /**
     * Every table that still names a currency in its schema. The documents
     * (invoices, payments, orders, receipts, expenses) and the parties whose
     * default currency seeds them (customers, suppliers), plus the chart of
     * accounts, which had riyal-denominated accounts inserted into a ledger
     * that has only ever posted in the base currency.
     */
    private const TABLES = [
        'invoices',
        'payments',
        'sales_orders',
        'expenses',
        'purchase_orders',
        'purchase_receipts',
        'supplier_payments',
        'suppliers',
        'customers',
        'ledger_accounts',
    ];

    public function up(): void
    {
        // Changing a column default is raw DDL and not portable: SQLite, which
        // the test suite runs on, has no ALTER COLUMN at all. Nothing is lost
        // by skipping it there — a schema default is only the fallback for
        // rows inserted outside a model, and the test database is built from
        // these migrations every run anyway.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $base = base_currency_code();

        // Guard the interpolation below: this value reaches raw DDL, where a
        // bound parameter is not available for a DEFAULT clause.
        if (! preg_match('/^[A-Z]{3}$/', $base)) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` ALTER COLUMN `currency` SET DEFAULT '{$base}'");
        }
    }

    public function down(): void
    {
        // Intentionally not restored. The previous default was the defect this
        // migration exists to remove, and putting 'SAR' back would silently
        // start mislabelling documents again on any books not based in riyals.
    }
};
