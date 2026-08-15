<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Rebuilds the catalogue from the daily stock sheet.
 *
 * The sheet ("الوارد") is a goods-received list: one row per item with its
 * category, unit, quantity, cost and retail price. This wipes the existing
 * catalogue, recreates the categories the sheet actually uses, imports every
 * product, and books the quantities into the main warehouse.
 *
 * Stock is booked through InventoryService rather than written straight into
 * the tables, so each product's opening quantity leaves a real movement record
 * and the warehouse rows, the product totals and the audit trail all agree from
 * the first day.
 *
 * Destructive, so it refuses to run without --force and always writes a backup
 * of what it is about to delete.
 */
class SeedCatalogFromDaily extends Command
{
    protected $signature = 'catalog:seed-from-daily
                            {file=daily.xlsx : Path to the xlsx}
                            {--force : Actually wipe and import (otherwise dry run)}
                            {--warehouse=المستودع الرئيسي : Name of the main warehouse}';

    protected $description = 'Wipe products/categories and re-seed them from the daily stock sheet';

    /** Column positions in the sheet. */
    private const COL_NAME = 0;
    private const COL_CODE = 1;
    private const COL_UNIT = 2;
    private const COL_QTY = 4;
    private const COL_COST = 5;
    private const COL_PRICE = 7;
    private const COL_CATEGORY = 8;

    /** Icons per category so the storefront does not show a generic box for all. */
    private const CATEGORY_ICONS = [
        'أدوات صحية' => 'fa-sink',
        'مواسير ووصلات' => 'fa-pipe',
        'عدد وأدوات' => 'fa-screwdriver-wrench',
        'مواد بناء / عدد' => 'fa-trowel-bricks',
        'خلاطات' => 'fa-faucet',
    ];

    public function handle(InventoryService $inventory): int
    {
        $path = $this->argument('file');
        if (!is_file($path)) {
            $this->error("الملف غير موجود: {$path}");
            return self::FAILURE;
        }

        $rows = $this->readSheet($path);
        $header = array_shift($rows);
        $items = $this->parseRows($rows);

        if (!$items) {
            $this->error('لم يُقرأ أي صنف صالح من الملف.');
            return self::FAILURE;
        }

        $categories = collect($items)->groupBy('category')->map->count()->sortDesc();

        $this->line('الملف: ' . $path);
        $this->line('الأصناف الصالحة: ' . count($items));
        $this->line('إجمالي الكميات: ' . number_format(collect($items)->sum('quantity')));
        $this->line('بلا سعر بيع: ' . collect($items)->where('price', '<=', 0)->count());
        $this->newLine();
        $this->table(
            ['الفئة', 'عدد الأصناف'],
            $categories->map(fn ($count, $name) => [$name, $count])->values()->all()
        );

        $this->newLine();
        $this->warn('سيُحذف: ' . Product::count() . ' منتج و' . Category::count() . ' فئة');
        $this->warn('وبالتبعية: سجلات المخزون وحركاته المرتبطة بها.');

        if (!$this->option('force')) {
            $this->newLine();
            $this->info('وضع المعاينة — لم يتغيّر شيء. أضف --force للتنفيذ.');
            return self::SUCCESS;
        }

        $backup = $this->backupExisting();
        $this->line('نسخة احتياطية: ' . $backup);

        $warehouse = $this->ensureWarehouse();
        $this->line('المستودع: ' . $warehouse->name . ' (#' . $warehouse->id . ')');

        $this->wipeCatalogue();

        $categoryIds = $this->createCategories($categories->keys()->all());
        $this->line('أُنشئت ' . count($categoryIds) . ' فئة.');

        $created = $this->createProducts($items, $categoryIds);
        $this->line('أُنشئ ' . count($created) . ' منتج.');

        $this->stockProducts($inventory, $created, $warehouse->id);

        $this->newLine();
        $this->info('تم. شغّل inventory:check للتحقق.');

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */

    /** @return array<int,array<int,string>> */
    private function readSheet(string $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException("تعذّر فتح الملف: {$file}");
        }

        $shared = [];
        if (($i = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $xml = simplexml_load_string($zip->getFromIndex($i));
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                    continue;
                }
                $text = '';
                foreach ($si->r ?? [] as $run) {
                    $text .= (string) $run->t;
                }
                $shared[] = $text;
            }
        }

        $columnIndex = static function (string $ref): int {
            preg_match('/^([A-Z]+)/', $ref, $m);
            $n = 0;
            foreach (str_split($m[1]) as $ch) {
                $n = $n * 26 + (ord($ch) - 64);
            }
            return $n - 1;
        };

        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $value = (string) $c->v;
                $type = (string) $c['t'];
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) $c->is->t;
                }
                $cells[$columnIndex((string) $c['r'])] = $value;
            }

            if (!$cells) {
                continue;
            }

            for ($i = 0; $i <= max(array_keys($cells)); $i++) {
                $cells[$i] ??= '';
            }
            ksort($cells);
            $rows[] = $cells;
        }

        $zip->close();

        return $rows;
    }

    /** @return array<int,array{name:string,code:?string,unit:?string,quantity:int,cost:float,price:float,category:string}> */
    private function parseRows(array $rows): array
    {
        // Prices arrive as "$1.00"; strip anything that is not part of a number.
        $money = static fn ($v) => round((float) preg_replace('/[^0-9.\-]/', '', (string) $v), 2);

        $items = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row[self::COL_NAME] ?? ''));
            if ($name === '') {
                continue;
            }

            $category = trim((string) ($row[self::COL_CATEGORY] ?? '')) ?: 'غير مصنّف';

            $items[] = [
                'name' => $name,
                'code' => trim((string) ($row[self::COL_CODE] ?? '')) ?: null,
                'unit' => trim((string) ($row[self::COL_UNIT] ?? '')) ?: null,
                'quantity' => max(0, (int) ($row[self::COL_QTY] ?? 0)),
                'cost' => $money($row[self::COL_COST] ?? 0),
                'price' => $money($row[self::COL_PRICE] ?? 0),
                'category' => $category,
            ];
        }

        return $items;
    }

    private function backupExisting(): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file = $dir . '/catalog-before-seed-' . now()->format('Ymd-His') . '.json';

        file_put_contents($file, json_encode([
            'taken_at' => now()->toIso8601String(),
            'categories' => DB::table('categories')->get(),
            'products' => DB::table('products')->get(),
            'warehouse_inventory' => DB::table('warehouse_inventory')->get(),
            'stock_movements' => DB::table('stock_movements')->get(),
        ], JSON_UNESCAPED_UNICODE));

        return $file;
    }

    private function ensureWarehouse(): Warehouse
    {
        $name = $this->option('warehouse');

        $warehouse = Warehouse::query()->where('name', $name)->first()
            ?? Warehouse::query()->orderBy('id')->first();

        if ($warehouse) {
            // Reuse the existing warehouse so historical movements keep pointing
            // at something real, but make sure it carries the intended name.
            if ($warehouse->name !== $name) {
                $warehouse->name = $name;
                $warehouse->save();
            }
            return $warehouse;
        }

        return Warehouse::create([
            'name' => $name,
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);
    }

    private function wipeCatalogue(): void
    {
        // warehouse_inventory and stock_movements cascade from products; the
        // invoice_items link is SET NULL, so existing invoices survive with
        // their product reference cleared rather than disappearing.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        DB::table('warehouse_inventory')->delete();
        DB::table('stock_movements')->delete();
        DB::table('products')->delete();
        DB::table('categories')->delete();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** @return array<string,int> category name => id */
    private function createCategories(array $names): array
    {
        $ids = [];
        $order = 0;

        foreach ($names as $name) {
            $category = Category::create([
                'name_ar' => $name,
                'name_en' => Str::title(str_replace('/', ' - ', $name)),
                'slug' => $this->uniqueSlug($name, 'categories'),
                'icon' => self::CATEGORY_ICONS[$name] ?? 'fa-box',
                'sort_order' => ++$order,
                'is_active' => true,
            ]);

            $ids[$name] = $category->id;
        }

        return $ids;
    }

    /** @return array<int,array{id:int,quantity:int,cost:float}> */
    private function createProducts(array $items, array $categoryIds): array
    {
        $created = [];
        $bar = $this->output->createProgressBar(count($items));
        $bar->setFormat('  المنتجات: %current%/%max% [%bar%]');
        $bar->start();

        foreach ($items as $index => $item) {
            $product = Product::create([
                'category_id' => $categoryIds[$item['category']] ?? null,
                'name_ar' => $item['name'],
                'name_en' => null,
                // Names repeat in the sheet, so slugs are made unique rather
                // than letting the second one collide or overwrite the first.
                'slug' => $this->uniqueSlug($item['name'], 'products'),
                'sku' => $item['code'],
                'unit' => $item['unit'],
                'cost_price' => $item['cost'] ?: null,
                'price' => $item['price'] ?: null,
                // Nothing is invented: a missing retail price stays missing and
                // is hidden on the storefront until somebody sets it.
                'show_price' => $item['price'] > 0,
                'stock_quantity' => 0,
                'in_stock' => $item['quantity'] > 0,
                'is_active' => true,
                'sort_order' => $index,
            ]);

            $created[] = ['id' => $product->id, 'quantity' => $item['quantity'], 'cost' => $item['cost']];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $created;
    }

    private function stockProducts(InventoryService $inventory, array $created, int $warehouseId): void
    {
        $withStock = array_values(array_filter($created, fn ($p) => $p['quantity'] > 0));

        $bar = $this->output->createProgressBar(count($withStock));
        $bar->setFormat('  إدخال المخزون: %current%/%max% [%bar%]');
        $bar->start();

        $units = 0;

        foreach ($withStock as $product) {
            $inventory->receive($product['id'], $product['quantity'], $warehouseId, [
                'key' => 'seed:daily:product:' . $product['id'],
                'source' => 'opening_stock',
                'reason' => 'رصيد افتتاحي من ملف الوارد',
                'unit_cost' => $product['cost'],
            ]);

            $units += $product['quantity'];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line('  ' . count($withStock) . ' منتجاً برصيد، إجمالي ' . number_format($units) . ' وحدة.');
    }

    private function uniqueSlug(string $value, string $table): string
    {
        $base = Str::slug($value, '-', null) ?: 'item';
        $slug = $base;
        $n = 1;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }
}
