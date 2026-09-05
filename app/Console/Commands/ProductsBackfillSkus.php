<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * One-time backfill that hands a SKU to every product that has none.
 *
 * 1,600 of the products in the table carry no SKU: the legacy "L182" codes
 * were assigned by hand and only cover part of the catalogue, so a product
 * added since has usually been stored without one. Each missing product is
 * given the next code from the "SKU-00001" series — deliberately its own
 * series, not an extension of the hand-assigned codes.
 *
 * Existing SKUs are left untouched. `--dry-run` reports what would be issued
 * without writing anything.
 *
 * The whole series is computed in one pass rather than calling nextSku() per
 * product: that method scans for the current maximum each time, so a backfill
 * of a thousand products turns into a thousand table scans. Here the standing
 * maximum is read once and the available codes are walked in memory, which
 * mirrors nextSku()'s rules exactly.
 */
class ProductsBackfillSkus extends Command
{
    protected $signature = 'products:backfill-skus {--dry-run : Report what would be issued without saving}';

    protected $description = 'Issue the next auto SKU to every product that has none';

    public function handle(): int
    {
        $missing = Product::whereNull('sku')
            ->orWhere('sku', '')
            ->orderBy('id')
            ->pluck('name_ar', 'id');

        if ($missing->isEmpty()) {
            $this->info('لا يوجد منتجات بدون SKU.');
            return self::SUCCESS;
        }

        $taken = Product::whereNotNull('sku')
            ->pluck('sku')
            ->filter(fn ($sku) => $sku !== '')
            ->flip()
            ->all();

        $rows = [];
        $next = $this->nextNumber($taken);

        $this->newLine();
        $this->line('— توليد SKU للمنتجات التي لا تملك واحداً');
        $this->newLine();

        foreach ($missing as $id => $name) {
            do {
                $code = 'SKU-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
                $next++;
            } while (isset($taken[$code]));

            $taken[$code] = true;

            $rows[] = [
                $id,
                mb_substr((string) $name, 0, 30),
                $code,
            ];

            if (! $this->option('dry-run')) {
                Product::where('id', $id)->update(['sku' => $code]);
            }
        }

        $this->table(
            ['المنتج', 'الاسم', 'SKU الجديد'],
            $rows
        );

        $this->newLine();
        $verb = $this->option('dry-run') ? 'سيُمنح' : 'تم توليد';
        $this->info("  {$verb} SKU عدد: ".count($rows));

        return self::SUCCESS;
    }

    /**
     * The first number in the "SKU-00001" series not already taken.
     *
     * The same walk nextSku() performs: the highest code in the series from
     * the table, then skip anything (any prefix, legacy codes included) that
     * happens to equal a candidate.
     */
    private function nextNumber(array $taken): int
    {
        $numbers = array_keys($taken);

        $highest = 0;
        foreach ($numbers as $code) {
            if (is_string($code) && str_starts_with($code, 'SKU-') && is_numeric(substr($code, 4))) {
                $highest = max($highest, (int) substr($code, 4));
            }
        }

        do {
            $highest++;
        } while (isset($taken['SKU-'.str_pad((string) $highest, 5, '0', STR_PAD_LEFT)]));

        return $highest;
    }
}