<?php

namespace App\Console\Commands;

use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Integrity check for stock.
 *
 * Inventory lives in three places that must agree:
 *
 *   warehouse_inventory      the per-warehouse position (what the WMS uses)
 *   products.stock_quantity  the cached company-wide total
 *   stock_movements          the audit trail of everything that moved
 *
 * They are maintained by different code paths, so any direct SQL edit, failed
 * transaction, or write that bypasses InventoryService leaves them disagreeing
 * while every screen keeps trusting whichever one it happens to read.
 *
 * `--fix` re-derives `products.stock_quantity` from `warehouse_inventory`,
 * which is the safe direction: the warehouse rows carry the detail, the product
 * column is only a cache of their sum. Nothing else is auto-corrected — a
 * negative balance or an internally inconsistent warehouse row means somebody
 * has to decide what the real quantity is.
 */
class InventoryCheck extends Command
{
    protected $signature = 'inventory:check {--fix : Recompute products.stock_quantity from warehouse_inventory}';

    protected $description = 'Verify stock integrity across warehouse rows, product totals and movements';

    public function handle(): int
    {
        $problems = 0;

        $problems += $this->checkNegativeStock();
        $problems += $this->checkBucketConsistency();
        $problems += $this->checkOverReservation();
        $problems += $this->checkProductTotals();
        $problems += $this->checkOrphanedStock();

        $this->newLine();

        if ($problems === 0) {
            $this->info('✔ المخزون سليم — لا توجد مشاكل.');
            return self::SUCCESS;
        }

        $this->warn("انتهى الفحص: {$problems} مشكلة.");

        return self::FAILURE;
    }

    /** Stock cannot be less than nothing; if it is, something shipped twice. */
    private function checkNegativeStock(): int
    {
        $this->line('— كميات سالبة');

        $rows = DB::table('warehouse_inventory as wi')
            ->join('products as p', 'p.id', '=', 'wi.product_id')
            ->where(function ($q) {
                $q->where('wi.quantity', '<', 0)->orWhere('wi.available_quantity', '<', 0);
            })
            ->select('wi.warehouse_id', 'p.id as product_id', 'p.name_ar', 'wi.quantity', 'wi.available_quantity')
            ->limit(50)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('  لا يوجد.');
            return 0;
        }

        $this->table(
            ['المستودع', 'المنتج', 'الاسم', 'الكمية', 'المتاح'],
            $rows->map(fn ($r) => [
                $r->warehouse_id,
                $r->product_id,
                mb_substr((string) $r->name_ar, 0, 30),
                $r->quantity,
                $r->available_quantity,
            ])->all()
        );

        return $rows->count();
    }

    /**
     * `quantity` is the whole shelf; the three condition buckets are how it
     * splits. If they do not add up, one of the writers is updating the total
     * without the bucket (or the other way round).
     */
    private function checkBucketConsistency(): int
    {
        $this->newLine();
        $this->line('— تطابق الكمية الإجمالية مع تفاصيلها');

        $rows = DB::table('warehouse_inventory')
            ->whereRaw('quantity <> (available_quantity + damaged_quantity + quarantined_quantity)')
            ->selectRaw('warehouse_id, product_id, quantity, available_quantity, damaged_quantity, quarantined_quantity,
                         (available_quantity + damaged_quantity + quarantined_quantity) as parts')
            ->limit(50)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('  متطابقة.');
            return 0;
        }

        $this->table(
            ['المستودع', 'المنتج', 'الإجمالي', 'متاح', 'تالف', 'حجر', 'مجموع التفاصيل'],
            $rows->map(fn ($r) => [
                $r->warehouse_id, $r->product_id, $r->quantity,
                $r->available_quantity, $r->damaged_quantity, $r->quarantined_quantity, $r->parts,
            ])->all()
        );

        return $rows->count();
    }

    /** More units promised to orders than exist on the shelf. */
    private function checkOverReservation(): int
    {
        $this->newLine();
        $this->line('— حجوزات تتجاوز المتاح');

        $rows = DB::table('warehouse_inventory')
            ->whereRaw('reserved_quantity > available_quantity')
            ->select('warehouse_id', 'product_id', 'available_quantity', 'reserved_quantity')
            ->limit(50)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('  لا يوجد.');
            return 0;
        }

        $this->table(
            ['المستودع', 'المنتج', 'المتاح', 'المحجوز'],
            $rows->map(fn ($r) => [$r->warehouse_id, $r->product_id, $r->available_quantity, $r->reserved_quantity])->all()
        );

        return $rows->count();
    }

    /** The cached product total against the sum of its warehouse rows. */
    private function checkProductTotals(): int
    {
        $this->newLine();
        $this->line('— رصيد المنتج مقابل مجموع المستودعات');

        $rows = DB::table('products as p')
            ->leftJoin('warehouse_inventory as wi', 'wi.product_id', '=', 'p.id')
            ->groupBy('p.id', 'p.name_ar', 'p.stock_quantity')
            ->havingRaw('p.stock_quantity <> COALESCE(SUM(wi.quantity), 0)')
            ->selectRaw('p.id, p.name_ar, p.stock_quantity, COALESCE(SUM(wi.quantity),0) as warehouse_total')
            // No limit here on purpose: the display is capped further down, but
            // --fix has to see every drifted product. Capping the query made a
            // single run repair only the first slice and report the rest as new
            // problems on the next run.
            ->get();

        if ($rows->isEmpty()) {
            $this->info('  متطابقة.');
            return 0;
        }

        $this->table(
            ['المنتج', 'الاسم', 'رصيد المنتج', 'مجموع المستودعات'],
            $rows->take(20)->map(fn ($r) => [
                $r->id,
                mb_substr((string) $r->name_ar, 0, 30),
                $r->stock_quantity,
                $r->warehouse_total,
            ])->all()
        );

        if ($rows->count() > 20) {
            $this->line('  ... و' . ($rows->count() - 20) . ' منتجاً آخر.');
        }

        if ($this->option('fix')) {
            foreach ($rows as $row) {
                DB::table('products')->where('id', $row->id)->update(['stock_quantity' => $row->warehouse_total]);
            }
            $this->info('  تم تصحيح ' . $rows->count() . ' رصيد من واقع المستودعات.');
            return 0;
        }

        $this->warn('  شغّل الأمر مع --fix لإعادة احتسابها من المستودعات.');

        return $rows->count();
    }

    /** Movements recorded against a warehouse row that no longer exists. */
    private function checkOrphanedStock(): int
    {
        $this->newLine();
        $this->line('— حركات بلا رصيد مستودع مقابل');

        $orphans = DB::table('stock_movements as sm')
            ->leftJoin('warehouse_inventory as wi', function ($join) {
                $join->on('wi.product_id', '=', 'sm.product_id')
                    ->on('wi.warehouse_id', '=', 'sm.warehouse_id');
            })
            ->whereNotNull('sm.warehouse_id')
            ->whereNull('wi.id')
            ->distinct()
            ->count(DB::raw('CONCAT(sm.product_id, "-", sm.warehouse_id)'));

        if (!$orphans) {
            $this->info('  لا يوجد.');
            return 0;
        }

        $this->warn("  {$orphans} تركيبة منتج/مستودع لها حركات دون سجل مخزون.");

        return $orphans;
    }
}
