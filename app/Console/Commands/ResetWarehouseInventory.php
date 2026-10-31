<?php

namespace App\Console\Commands;

use App\Models\LedgerAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resets all warehouse inventory data, stock quantities across products and variants,
 * stock movement history, FIFO cost layers, and related opening inventory journal entries.
 */
class ResetWarehouseInventory extends Command
{
    protected $signature = 'inventory:reset
                            {--force : Skip the confirmation prompt}
                            {--skip-backup : Skip taking an automatic database backup}';

    protected $description = 'Wipe all warehouse inventory data, stock movements, cost layers, and opening inventory entries';

    public function handle(): int
    {
        $wiCount = Schema::hasTable('warehouse_inventory') ? DB::table('warehouse_inventory')->count() : 0;
        $smCount = Schema::hasTable('stock_movements') ? DB::table('stock_movements')->count() : 0;
        $clCount = Schema::hasTable('inventory_cost_layers') ? DB::table('inventory_cost_layers')->count() : 0;
        $prodCount = Schema::hasTable('products') ? DB::table('products')->where('stock_quantity', '!=', 0)->count() : 0;
        $prodStock = Schema::hasTable('products') ? (int) DB::table('products')->sum('stock_quantity') : 0;
        $varCount = (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'stock_quantity'))
            ? DB::table('product_variants')->where('stock_quantity', '!=', 0)->count() : 0;

        $openingHeaderIds = DB::table('journal_entry_headers')
            ->where('posting_key', 'like', 'opening_balance:inventory:%')
            ->pluck('id')
            ->all();
        $jeCount = count($openingHeaderIds);
        $jlCount = ! empty($openingHeaderIds)
            ? DB::table('journal_entry_lines')->whereIn('journal_entry_header_id', $openingHeaderIds)->count()
            : 0;

        $this->warn('سيتم حذف وتصفير بيانات مخزون المستودعات الآتية:');
        $this->table(
            ['البند (Item)', 'العدد / القيمة (Count / Value)'],
            [
                ['سجلات مخزون المستودعات (warehouse_inventory)', $wiCount],
                ['حركات المخزون (stock_movements)', $smCount],
                ['طبقات التكلفة FIFO (inventory_cost_layers)', $clCount],
                ['منتجات ذات رصيد مخزون (products)', "{$prodCount} منتج (إجمالي {$prodStock} قطعة)"],
                ['متغيرات منتجات ذات رصيد (product_variants)', "{$varCount} متغير"],
                ['قيود الرصيد الافتتاحي للمخزون بالدفتر (journal_entry_headers)', $jeCount],
                ['سطور قيود الرصيد الافتتاحي للمخزون (journal_entry_lines)', $jlCount],
            ]
        );

        $this->line('سيتم تصفير رصيد المخزون لجميع المنتجات والمتغيرات إلى 0.');
        $this->line('سيتم تصفير حسابات المخزون ورأس المال المقابلة لها في دليل الحسابات.');
        $this->newLine();
        $this->info('لن يُمس: تعريفات المستودعات، المنتجات والتصنيفات، وبقية إعدادات النظام.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('هل أنت متأكد من رغبتك في متابعة تصفير المخزون؟', false)) {
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

        $this->info('جاري تصفير بيانات المخزون...');

        $tablesToWipe = ['warehouse_inventory', 'stock_movements', 'inventory_cost_layers'];

        DB::transaction(function () use ($tablesToWipe, $openingHeaderIds) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            // 1. Wipe inventory records
            foreach ($tablesToWipe as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            // 2. Reset product and variant stock quantities
            if (Schema::hasTable('products') && Schema::hasColumn('products', 'stock_quantity')) {
                DB::table('products')->update(['stock_quantity' => 0]);
            }

            if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'stock_quantity')) {
                DB::table('product_variants')->update(['stock_quantity' => 0]);
            }

            // 3. Remove opening balance journal entries for inventory
            if (! empty($openingHeaderIds)) {
                DB::table('journal_entry_lines')->whereIn('journal_entry_header_id', $openingHeaderIds)->delete();
                DB::table('journal_entry_headers')->whereIn('id', $openingHeaderIds)->delete();
            }

            // 4. Zero the balances of inventory accounts and capital
            if (Schema::hasTable('ledger_accounts')) {
                // If no other journal entries exist, all ledger account balances should be 0
                $hasOtherEntries = DB::table('journal_entry_headers')
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['draft', 'pending', 'void', 'cancelled'])
                    ->exists();

                if (! $hasOtherEntries) {
                    DB::table('ledger_accounts')->update(['balance' => 0, 'opening_balance' => 0]);
                } else {
                    // Recalculate each account from remaining journal entries
                    $sums = DB::table('journal_entry_lines as l')
                        ->join('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
                        ->whereNull('h.deleted_at')
                        ->whereNotIn('h.status', ['draft', 'pending', 'void', 'cancelled'])
                        ->groupBy('l.account_id')
                        ->selectRaw('l.account_id, COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')
                        ->get()
                        ->keyBy('account_id');

                    foreach (LedgerAccount::all() as $account) {
                        $row = $sums->get($account->id);
                        $derived = round(LedgerAccount::signedDelta(
                            $account->type,
                            (float) ($row->d ?? 0),
                            (float) ($row->c ?? 0)
                        ), 2);
                        $account->update(['balance' => $derived]);
                    }
                }
            }

            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        // Reset auto increments outside transaction
        $this->resetAutoIncrements(array_merge($tablesToWipe, ['journal_entry_lines', 'journal_entry_headers']));

        // Clear application cache
        Cache::flush();
        $this->info('تم مسح الذاكرة المؤقتة (Cache).');

        $this->newLine();
        $this->info('✓ تم تصفير بيانات مخزون المستودعات بالكامل بنجاح.');
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
     */
    private function resetAutoIncrements(array $tables): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                // Only reset auto-increment if the table is currently empty
                if (DB::table($table)->count() === 0) {
                    try {
                        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }
        }
    }
}
