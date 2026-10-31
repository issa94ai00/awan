<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resets all sales and purchase business transactions while preserving
 * products, categories, warehouse inventory stock, cost layers, and accounts.
 */
class ResetSalesAndPurchases extends Command
{
    protected $signature = 'transactions:reset
                            {--force : Skip the confirmation prompt}
                            {--skip-backup : Skip taking an automatic database backup}
                            {--without-quotes : Preserve sales quotes ( عروض الأسعار )}';

    protected $description = 'Wipe all sales and purchase transactions and release reserved stock';

    /**
     * Sales transaction tables in reverse dependency order.
     */
    private const SALES_TABLES = [
        'sales_order_item_allocations',
        'sales_order_routings',
        'sales_order_status_histories',
        'sales_order_items',
        'sales_orders',
        'picking_list_items',
        'picking_lists',
        'packing_list_items',
        'packing_lists',
        'shipping_manifest_items',
        'shipping_manifests',
        'sales_contracts',
        'order_items',
        'orders',
        'rma_items',
        'rma_requests',
        'invoice_items',
        'invoices',
        'credit_note_items',
        'credit_notes',
        'payments',
    ];

    /**
     * Quotation tables.
     */
    private const QUOTE_TABLES = [
        'quote_items',
        'quotes',
    ];

    /**
     * Purchase transaction tables in reverse dependency order.
     */
    private const PURCHASE_TABLES = [
        'purchase_return_items',
        'purchase_returns',
        'purchase_order_items',
        'purchase_orders',
        'purchase_receipt_items',
        'purchase_receipts',
        'supplier_payments',
        'landed_costs',
    ];

    public function handle(): int
    {
        $tablesToWipe = array_merge(
            self::SALES_TABLES,
            $this->option('without-quotes') ? [] : self::QUOTE_TABLES,
            self::PURCHASE_TABLES
        );

        $counts = $this->getTableCounts($tablesToWipe);
        $totalRecords = array_sum($counts);

        $this->warn('سيتم حذف حركة المبيعات والمشتريات الآتية:');
        $this->table(
            ['الجدول (Table)', 'عدد السجلات (Count)'],
            collect($counts)
                ->filter(fn ($n) => $n > 0)
                ->map(fn ($n, $tbl) => [$tbl, $n])
                ->values()
                ->all()
        );

        $this->line("إجمالي السجلات التي سيتم حذفها: {$totalRecords}");
        $this->line('سيتم تصفير الكميات المحجوزة (reserved_quantity) في المستودعات.');
        $this->line('سيتم تصفير إحصائيات مشتريات وأرصدة العملاء والموردين.');
        $this->newLine();
        $this->info('لن يُمس: المنتجات، المستودعات، كميات المخزون المتوفرة، طبقات التكلفة، دليل الحسابات والمستخدمين.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('هل أنت متأكد من رغبتك في متابعة الحذف؟', false)) {
            $this->line('أُلغيت العملية.');

            return self::SUCCESS;
        }

        // Automatic backup before proceeding
        if (! $this->option('skip-backup')) {
            $this->info('جاري أخذ نسخة احتياطية من قاعدة البيانات...');
            $backupExitCode = Artisan::call('db:backup');
            $this->line(trim(Artisan::output()));

            if ($backupExitCode !== 0) {
                $this->error('فشل إنشاء النسخة الاحتياطية. تم إيقاف العملية للحفاظ على أمان البيانات.');

                return self::FAILURE;
            }
        }

        $this->info('جاري تصفية المعاملات...');

        DB::transaction(function () use ($tablesToWipe) {
            $this->wipeTables($tablesToWipe);
            $this->cleanupSideEffects();
        });

        // Reset auto-increment outside transaction (MySQL DDL causes implicit commit)
        $this->resetAutoIncrements($tablesToWipe);

        // Clear application cache
        Cache::flush();
        $this->info('تم مسح الذاكرة المؤقتة (Cache).');

        $this->newLine();
        $this->info('✓ تمت تصفية معاملات المبيعات والمشتريات بنجاح.');
        $this->newLine();

        // Run integrity checks
        $this->line('التحقق من سلامة المخزون:');
        Artisan::call('inventory:check');
        $this->line(trim(Artisan::output()));

        $this->newLine();
        $this->line('التحقق من سلامة القيود المحاسبية:');
        Artisan::call('accounting:check');
        $this->line(trim(Artisan::output()));

        return self::SUCCESS;
    }

    /**
     * @param  array<string>  $tables
     * @return array<string, int>
     */
    private function getTableCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    /**
     * @param  array<string>  $tables
     */
    private function wipeTables(array $tables): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @param  array<string>  $tables
     */
    private function resetAutoIncrements(array $tables): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                try {
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                } catch (\Throwable $e) {
                    // ignore if table doesn't support auto-increment reset
                }
            }
        }
    }

    /**
     * Clean up operational metrics and references linked to wiped transactions.
     */
    private function cleanupSideEffects(): void
    {
        // 1. Release all reserved inventory quantities
        if (Schema::hasTable('warehouse_inventory') && Schema::hasColumn('warehouse_inventory', 'reserved_quantity')) {
            DB::table('warehouse_inventory')->update(['reserved_quantity' => 0]);
        }

        // 2. Clear party running purchase statistics and balances
        if (Schema::hasTable('customers')) {
            $customerUpdates = [];
            if (Schema::hasColumn('customers', 'balance')) {
                $customerUpdates['balance'] = 0;
            }
            if (Schema::hasColumn('customers', 'total_purchases')) {
                $customerUpdates['total_purchases'] = 0;
            }
            if (Schema::hasColumn('customers', 'last_purchase_at')) {
                $customerUpdates['last_purchase_at'] = null;
            }
            if (! empty($customerUpdates)) {
                DB::table('customers')->update($customerUpdates);
            }
        }

        if (Schema::hasTable('suppliers')) {
            $supplierUpdates = [];
            if (Schema::hasColumn('suppliers', 'balance')) {
                $supplierUpdates['balance'] = 0;
            }
            if (Schema::hasColumn('suppliers', 'total_purchases')) {
                $supplierUpdates['total_purchases'] = 0;
            }
            if (! empty($supplierUpdates)) {
                DB::table('suppliers')->update($supplierUpdates);
            }
        }

        // 3. Clear foreign links in expenses if any exist
        if (Schema::hasTable('expenses')) {
            $expenseUpdates = [];
            if (Schema::hasColumn('expenses', 'sales_order_id')) {
                $expenseUpdates['sales_order_id'] = null;
            }
            if (Schema::hasColumn('expenses', 'invoice_id')) {
                $expenseUpdates['invoice_id'] = null;
            }
            if (! empty($expenseUpdates)) {
                DB::table('expenses')->update($expenseUpdates);
            }
        }

        // 4. Clear serial number allocations if any exist
        if (Schema::hasTable('product_serial_numbers')) {
            $serialUpdates = [];
            if (Schema::hasColumn('product_serial_numbers', 'sale_order_id')) {
                $serialUpdates['sale_order_id'] = null;
            }
            if (Schema::hasColumn('product_serial_numbers', 'sale_order_item_id')) {
                $serialUpdates['sale_order_item_id'] = null;
            }
            if (! empty($serialUpdates)) {
                DB::table('product_serial_numbers')->update($serialUpdates);
            }
        }

        // 5. Delete order-related notifications and pending order alerts
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('type', 'order')
                ->orWhere('data', 'like', '%ops.pending_sales_orders%')
                ->delete();
        }
    }
}
