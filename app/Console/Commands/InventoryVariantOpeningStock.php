<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Brings the warehouse stock of products with variants up to what their
 * variants say is on hand.
 *
 * Warehouse stock, reservations and cost are counted per product. The
 * variants' own counts were typed in by hand and never received into any
 * warehouse, so a product whose sizes hold 305 units showed 0 in stock — and
 * every sales order for one of its sizes was refused as out of stock.
 *
 * For each product with variants this takes the difference between the sum
 * of its variants' counts and what its warehouse rows hold, and receives that
 * difference into one warehouse as opening stock: the stock layers get the
 * variants' cost, and nothing is posted to the ledger here. The value is put
 * on the books afterwards with `accounting:opening-balance --inventory`,
 * against capital, which is what stock the business already owned is.
 *
 * Most variants have no cost. With `--estimate-cost` a variant with none is
 * valued at its sale price times what cost runs at in its category — the
 * median cost/price of the category's products that have both, or of the
 * whole catalogue where the category has too few — and `--cost-ratio` fixes
 * one ratio instead. A product priced per pack ("سعر المئة") has its price
 * split by the pack size first, since its count is read as pieces. Estimated
 * rows are marked in the report, and the estimate only goes onto the stock
 * layers: the catalogue's cost stays empty, so the real one can still be
 * entered.
 *
 * Nothing moves without `--apply`. Without it the command writes a CSV for
 * review — with each product flagged where its figures need a second look —
 * and stops. Only additions are made: a product holding more than its
 * variants add up to is reported and left alone. Each product is received
 * under its own key, so running `--apply` twice adds nothing the second time.
 */
class InventoryVariantOpeningStock extends Command
{
    protected $signature = 'inventory:variant-opening-stock
                            {--warehouse= : Warehouse id to receive into (defaults to the primary warehouse)}
                            {--only= : Comma-separated product ids to include}
                            {--exclude= : Comma-separated product ids to leave out}
                            {--estimate-cost : Value a variant with no cost at its price times the cost/price ratio of its category}
                            {--cost-ratio= : With --estimate-cost, one fixed cost/price ratio instead of the category medians}
                            {--apply : Receive the stock (without it, only the report is written)}
                            {--report= : Where to write the CSV (defaults to storage/app/reports)}';

    protected $description = 'Receive opening stock for products whose variants hold more than their warehouses';

    /** Names ending "سعر الالف / المئة / العشرة" are priced per pack of that many. */
    private const PACK_PRICED = '/سعر\s*(ال)?(الف|ألف|المئة|المية|المائة|العشرة|الدرزن|الدزينة)/u';

    private const LARGE_QUANTITY = 1000;

    /**
     * "/ دزينة", "/ علبة" at the end of a name: sold by the pack, but whether
     * the price and count are per pack or per piece is not certain — some of
     * these are priced like single pieces. Reported, never divided.
     */
    private const PACK_NAMED = '/\/\s*(دزينة|درزن|علبة|كرتونة|طقم)\s*$/u';

    /** How many of each the pack-priced names mean. */
    private const PACK_SIZES = [
        'الف' => 1000, 'ألف' => 1000, 'المئة' => 100, 'المية' => 100, 'المائة' => 100,
        'العشرة' => 10, 'الدرزن' => 12, 'الدزينة' => 12,
    ];

    /** A category needs this many costed products for its own ratio. */
    private const MIN_RATIO_SAMPLE = 5;

    /** Cost/price ratios outside this are data errors, not margins. */
    private const RATIO_BOUNDS = [0.05, 1.5];

    /** @var array{global: float|null, categories: array<int, float>}|null */
    private ?array $ratios = null;

    public function handle(InventoryService $inventory): int
    {
        $warehouse = $this->option('warehouse')
            ? Warehouse::find((int) $this->option('warehouse'))
            : Warehouse::where('is_primary', true)->first() ?? Warehouse::find($inventory->defaultWarehouseId());

        if (! $warehouse) {
            $this->error('لم يُعثر على المستودع المطلوب.');

            return self::FAILURE;
        }

        $rows = $this->plan();

        if ($rows === []) {
            $this->info('لا توجد منتجات بمتغيرات تحتاج إلى رصيد — المخزون مطابق.');

            return self::SUCCESS;
        }

        $path = $this->writeReport($rows, $warehouse);
        $toAdd = array_values(array_filter($rows, fn ($row) => $row['to_add'] > 0));

        $this->summarise($rows, $toAdd, $warehouse, $path);

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('لم يُنقل أي مخزون. راجع التقرير، ثم أعد التشغيل مع --apply.');

            return self::SUCCESS;
        }

        $this->receive($inventory, $toAdd, $warehouse);

        return self::SUCCESS;
    }

    /**
     * One row per product with variants: what its variants add up to, what its
     * warehouses hold, what would be received and at what cost.
     *
     * @return list<array<string, mixed>>
     */
    private function plan(): array
    {
        $only = $this->ids('only');
        $exclude = $this->ids('exclude');

        $products = Product::query()
            ->whereHas('variants')
            ->when($only !== [], fn ($q) => $q->whereIn('id', $only))
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('id', $exclude))
            ->with('variants')
            ->orderBy('id')
            ->get();

        $held = DB::table('warehouse_inventory')
            ->whereIn('product_id', $products->pluck('id'))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->pluck('quantity', 'product_id');

        $rows = [];

        foreach ($products as $product) {
            $variantUnits = (int) $product->variants->sum(fn ($v) => max(0, (int) $v->stock_quantity));
            $warehouseUnits = (int) ($held[$product->id] ?? 0);
            $toAdd = max(0, $variantUnits - $warehouseUnits);

            // What the units being added cost: each variant's own cost where
            // it has one, the product's otherwise, and — when asked — an
            // estimate from its price; weighted by its count.
            $packSize = $this->packSize((string) $product->name_ar);
            $value = 0.0;
            $estimatedUnits = 0;
            $unpricedUnits = 0;
            // A size with no price of its own is priced like its siblings, not
            // like the product: the product's price is often a box or a typo
            // (a size typed as a price), and far off what one size sells for.
            $siblingPrice = $product->variants->pluck('price')->map(fn ($p) => (float) $p)->filter(fn ($p) => $p > 0)->median();
            foreach ($product->variants as $variant) {
                $units = max(0, (int) $variant->stock_quantity);
                $cost = (float) $variant->cost_price > 0 ? (float) $variant->cost_price : (float) $product->cost_price;

                if ($cost <= 0 && $this->option('estimate-cost')) {
                    $cost = $this->estimatedCost($product, $variant, $packSize, $siblingPrice ? (float) $siblingPrice : null);
                    if ($cost > 0) {
                        $estimatedUnits += $units;
                    }
                    if ((float) $variant->price <= 0 && $units > 0) {
                        $unpricedUnits += $units;
                    }
                }

                $value += $units * $cost;
            }
            $unitCost = $variantUnits > 0 ? round($value / $variantUnits, 5) : 0.0;

            $flags = [];
            if ($toAdd > 0 && $unitCost <= 0) {
                $flags[] = 'no_cost';
            }
            if ($estimatedUnits > 0) {
                $flags[] = $estimatedUnits === $variantUnits ? 'estimated_cost' : 'partly_estimated_cost';
            }
            if ($unpricedUnits > 0) {
                $flags[] = 'unpriced_variant';
            }
            if ($packSize !== null) {
                $flags[] = 'pack_priced';
            } elseif (preg_match(self::PACK_NAMED, (string) $product->name_ar)) {
                $flags[] = 'pack_named';
            }
            if ($variantUnits >= self::LARGE_QUANTITY) {
                $flags[] = 'large_quantity';
            }
            if ($warehouseUnits > $variantUnits) {
                $flags[] = 'warehouse_above_variants';
            }

            if ($toAdd === 0 && ! in_array('warehouse_above_variants', $flags, true)) {
                continue;
            }

            $rows[] = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name_ar,
                'variants' => $product->variants->count(),
                'variant_detail' => $product->variants
                    ->map(fn ($v) => ($v->label !== '' ? $v->label : ($v->sku ?: '#'.$v->id)).'='.(int) $v->stock_quantity)
                    ->implode('; '),
                'variant_units' => $variantUnits,
                'warehouse_units' => $warehouseUnits,
                'to_add' => $toAdd,
                'unit_cost' => $unitCost,
                'value' => round($toAdd * $unitCost, 2),
                'flags' => $flags,
            ];
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows */
    private function writeReport(array $rows, Warehouse $warehouse): string
    {
        $path = $this->option('report')
            ?: storage_path('app/reports/variant-opening-stock-'.now()->format('Y-m-d-His').'.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'w');
        // A byte-order mark, so Excel opens the Arabic names as Arabic.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'product_id', 'sku', 'name', 'variants', 'variant_counts', 'variant_units',
            'warehouse_units', 'to_add', 'unit_cost', 'value', 'flags', 'warehouse',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['product_id'], $row['sku'], $row['name'], $row['variants'], $row['variant_detail'],
                $row['variant_units'], $row['warehouse_units'], $row['to_add'], $row['unit_cost'],
                $row['value'], implode(' ', $row['flags']), $warehouse->name,
            ]);
        }

        fclose($handle);

        return $path;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $toAdd
     */
    private function summarise(array $rows, array $toAdd, Warehouse $warehouse, string $path): void
    {
        $flagged = fn (string $flag) => count(array_filter($rows, fn ($row) => in_array($flag, $row['flags'], true)));

        $this->info('رصيد افتتاحي لمنتجات المتغيرات → '.$warehouse->name.' (#'.$warehouse->id.')');
        $this->table(['', ''], [
            ['منتجات ستُضاف لها كمية', number_format(count($toAdd))],
            ['إجمالي الوحدات', number_format(array_sum(array_column($toAdd, 'to_add')))],
            ['القيمة بالتكلفة', number_format(array_sum(array_column($toAdd, 'value')), 2)],
            ['تكلفة تقديرية (estimated_cost)', number_format($flagged('estimated_cost') + $flagged('partly_estimated_cost'))],
            ['بلا تكلفة (no_cost) — تدخل بقيمة صفر', number_format($flagged('no_cost'))],
            ['تسعير بالعبوة (pack_priced) — تحقّق من الوحدة', number_format($flagged('pack_priced'))],
            ['اسم بعبوة «/ دزينة» (pack_named) — لم يُقسَم، تحقّق', number_format($flagged('pack_named'))],
            ['مقاس بلا سعر، قُدّر من أسعار المقاسات الأخرى (unpriced_variant)', number_format($flagged('unpriced_variant'))],
            ['كمية كبيرة (large_quantity ≥ '.self::LARGE_QUANTITY.')', number_format($flagged('large_quantity'))],
            ['رصيد المستودع أكبر من المتغيرات — لن يُمس', number_format($flagged('warehouse_above_variants'))],
        ]);
        if ($this->option('estimate-cost')) {
            $ratios = $this->ratios();
            $this->line($this->option('cost-ratio')
                ? 'نسبة التكلفة إلى السعر: '.$this->option('cost-ratio').' (ثابتة)'
                : 'نسبة التكلفة إلى السعر: وسيط كل فئة ('.count($ratios['categories']).' فئة)، وإلا وسيط الكتالوج '.round((float) $ratios['global'], 3));
        }
        $this->line('التقرير: '.$path);
    }

    /** @param list<array<string, mixed>> $rows */
    private function receive(InventoryService $inventory, array $rows, Warehouse $warehouse): void
    {
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $received = 0;
        $units = 0;

        foreach ($rows as $row) {
            $movement = $inventory->receive($row['product_id'], $row['to_add'], $warehouse->id, [
                // One key per product: a second --apply returns the movement
                // the first one wrote instead of adding the stock again.
                'key' => 'variant-opening:product:'.$row['product_id'],
                'source' => 'opening_stock',
                'reference' => 'variant_opening_stock',
                'reason' => 'رصيد افتتاحي من كميات المتغيرات',
                'unit_cost' => $row['unit_cost'],
                'created_by' => null,
            ]);

            if ($movement && $movement->wasRecentlyCreated) {
                $received++;
                $units += $row['to_add'];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('استُلم '.number_format($units).' وحدة لـ '.number_format($received).' منتجاً.');
        $this->line('لإثبات قيمتها في الدفاتر مقابل رأس المال: php artisan accounting:opening-balance --inventory --dry-run');
    }

    /**
     * A variant's cost estimated from what it sells for: its price (else the
     * median price of its sibling sizes, else the product's), per piece for a
     * pack-priced product, times its category's cost/price ratio.
     */
    private function estimatedCost(Product $product, $variant, ?int $packSize, ?float $siblingPrice): float
    {
        $price = (float) $variant->price > 0 ? (float) $variant->price : ($siblingPrice ?: (float) $product->price);
        if ($price <= 0) {
            return 0.0;
        }

        $ratio = $this->ratioFor($product->category_id);
        if ($ratio === null) {
            return 0.0;
        }

        return round($price / ($packSize ?: 1) * $ratio, 5);
    }

    private function ratioFor(?int $categoryId): ?float
    {
        if ($this->option('cost-ratio')) {
            return (float) $this->option('cost-ratio');
        }

        $ratios = $this->ratios();

        return $ratios['categories'][(int) $categoryId] ?? $ratios['global'];
    }

    /**
     * Median cost/price across the catalogue and per category, from the
     * products that carry both — the business's own margins, not a guess.
     *
     * @return array{global: float|null, categories: array<int, float>}
     */
    private function ratios(): array
    {
        if ($this->ratios !== null) {
            return $this->ratios;
        }

        [$low, $high] = self::RATIO_BOUNDS;
        $rows = Product::query()
            ->where('cost_price', '>', 0)
            ->where('price', '>', 0)
            ->get(['category_id', 'cost_price', 'price'])
            ->map(fn ($p) => ['category_id' => (int) $p->category_id, 'ratio' => (float) $p->cost_price / (float) $p->price])
            ->filter(fn ($row) => $row['ratio'] >= $low && $row['ratio'] <= $high);

        $categories = [];
        foreach ($rows->groupBy('category_id') as $categoryId => $group) {
            if ($group->count() >= self::MIN_RATIO_SAMPLE) {
                $categories[(int) $categoryId] = round((float) $group->pluck('ratio')->median(), 4);
            }
        }

        return $this->ratios = [
            'global' => $rows->isEmpty() ? null : round((float) $rows->pluck('ratio')->median(), 4),
            'categories' => $categories,
        ];
    }

    /** How many a pack-priced product's price is for, or null. */
    private function packSize(string $name): ?int
    {
        if (! preg_match(self::PACK_PRICED, $name, $match)) {
            return null;
        }

        return self::PACK_SIZES[$match[2]] ?? null;
    }

    /** @return list<int> */
    private function ids(string $option): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->option($option)))));
    }
}
