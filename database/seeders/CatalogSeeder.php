<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Rebuilds the catalogue from the goods-received sheet.
 *
 * Wipes every category and product, recreates the main warehouse, classifies
 * each item into a category, and books its opening quantity into that
 * warehouse. Re-runnable: it always starts from a clean catalogue.
 *
 * Stock is booked through InventoryService rather than written straight into
 * the tables, so each product's opening balance leaves a real movement record
 * and the warehouse rows, the product totals and the audit trail agree from the
 * first day.
 */
class CatalogSeeder extends Seeder
{
    private const DATA_FILE = 'data/daily-stock.xlsx';

    /** Column positions in the sheet. */
    private const COL_NAME = 0;
    private const COL_CODE = 1;
    private const COL_UNIT = 2;
    private const COL_QTY = 4;
    private const COL_COST = 5;
    private const COL_PRICE = 7;

    /**
     * Categories, in display order: slug key => [arabic, english, icon].
     */
    private const CATEGORIES = [
        'mixers' => ['خلاطات', 'Mixers & Faucets', 'fa-faucet'],
        'taps-valves' => ['حنفيات ومحابس', 'Taps & Valves', 'fa-droplet'],
        'sanitary' => ['أدوات صحية وإكسسوارات حمام', 'Sanitary Ware & Bath', 'fa-sink'],
        'pipes' => ['مواسير ووصلات', 'Pipes & Fittings', 'fa-pipe'],
        'tools' => ['عدد وأدوات يدوية', 'Hand Tools', 'fa-screwdriver-wrench'],
        'materials' => ['مواد بناء ولواصق', 'Building Materials & Adhesives', 'fa-trowel-bricks'],
        'furniture' => ['مفصلات وإكسسوارات أثاث', 'Hinges & Furniture Fittings', 'fa-door-open'],
        'electrical' => ['كهربائيات ومتفرقات', 'Electrical & Misc', 'fa-bolt'],
    ];

    /**
     * Ordered classification rules — the first pattern that matches wins.
     *
     * Order matters because names overlap: "وصلة كهربا" is electrical while
     * "وصلة بولاد" is a pipe fitting, and "طبة خلاط" belongs with mixers while
     * "طبة ليزرية" is furniture hardware. The narrower rule therefore has to be
     * tested before the broader one.
     *
     * @var array<int,array{0:string,1:string}> [regex, category key]
     */
    private const RULES = [
        // Electrical first: it shares words with pipes and tools.
        ['/كهربا|كاوي|فوشة|سلاسل ديكور|برادي/u', 'electrical'],

        // Furniture hardware that would otherwise be caught by the broader
        // sanitary rule below — "حمالة خزانين" is a cabinet bracket, while a
        // bare "حمالة" is a bathroom rack.
        ['/حمالة خزانين|tip on|دولاب عشراوية|زند طاقة|سكة خرادق|طبة ليزرية|مسكة|ترنلك/u', 'furniture'],

        // Mixer parts before the generic valve/tap rules.
        ['/خلاط|طقم شك|رقبة خلاط|طبة خلاط|قلب خلاط|عزقة خلاط|طبة خلاط/u', 'mixers'],
        ['/^رقبة|^طبة خلاط|قلب سكر شمسة|قلب نحاس|قلب خلاط/u', 'mixers'],

        // Bath and sanitary fittings before the broad "سكر/حنفية" valve rule,
        // because shower and toilet items often contain those words too.
        ['/شطاف|دوش|كرسي|مجلى|محلى|مغسلة|هراب|مصفاية|ريغار|ريكار|شراقة|برجور|وجاء حمام|اكسسوار حمام|غطا كرسي|تحويلة كرسي|عدة كرسي|صباب|سيفون|حمال[ةه]|بياض سكر|طاسة|سيخ سخان|عدة صندوق/u', 'sanitary'],

        // Taps and stop valves.
        ['/حنفية|^سكر|محبس|بصاب|فواشة|سحابة|كباسة|بربيش/u', 'taps-valves'],

        // Pipes and fittings.
        ['/^كوع|^تية|^تيه|^تي |^سدة|^سن |^سنة|^سنه|اوكر[ةها]|نقاصة|شد وصل|^بوري|بواري|وصلة|راكور|^مربط|جوان|تطويلة|^زاوية|^شمسة/u', 'pipes'],

        // Adhesives, sealants and consumables.
        ['/سليكون|ابوكسي|غراء|لزيو|زيرو ورق|تيب عزل|تيفلون|اسافين|معجون|ورق حف|ورق لزق|داموتليك|حجر قص|ديسك|^دسك|^دقر/u', 'materials'],

        // Furniture hardware.
        ['/مفصلة|مسكة|دولاب عشراوية|حمالة خزانين|زند طاقة|سكة خرادق|^قفل|ترنلك|طبة ليزرية|براغي/u', 'furniture'],

        // Hand tools last: broad words like "مفتاح" would otherwise swallow
        // plumbing items that merely mention them.
        ['/مفتاح|مطرقة|مفك|بانسة|^متر |ميزان|^مقص|مشرط|فراشي|مسطرين|مالج|كفوف|ريشة|مكواية|التيكو|طقم مسدس|بخاخ|خيط|حبل|مطاطة|فرد سليكون|طقم عزقة|حبسات/u', 'tools'],
    ];

    /** Anything no rule matches lands here so nothing is silently lost. */
    private const FALLBACK = 'sanitary';

    public function run(): void
    {
        $items = $this->readItems();

        if (!$items) {
            $this->command?->error('لم يُقرأ أي صنف من ملف البيانات.');
            return;
        }

        $this->command?->info('الأصناف المقروءة: ' . count($items));

        $this->wipeCatalogue();

        $categoryIds = $this->createCategories();
        $warehouse = $this->ensureWarehouse();

        $created = $this->createProducts($items, $categoryIds);
        $this->stockProducts($created, $warehouse->id);
        $this->linkRemaining($warehouse->id);

        $this->report($warehouse);
    }

    /* ------------------------------------------------------------------ */

    /** @return array<int,array{name:string,code:?string,unit:?string,quantity:int,cost:float,price:float}> */
    private function readItems(): array
    {
        $path = database_path('seeders/' . self::DATA_FILE);

        if (!is_file($path)) {
            throw new RuntimeException("ملف البيانات غير موجود: {$path}");
        }

        // Prices arrive as "$1.00" and occasionally as "10$", so everything that
        // is not part of a number is stripped rather than parsed positionally.
        $money = static fn ($v) => round((float) preg_replace('/[^0-9.\-]/', '', (string) $v), 2);

        $items = [];

        foreach ($this->readSheet($path) as $index => $row) {
            if ($index === 0) {
                continue; // header
            }

            $name = trim((string) ($row[self::COL_NAME] ?? ''));
            if ($name === '') {
                continue;
            }

            $items[] = [
                'name' => $name,
                'code' => trim((string) ($row[self::COL_CODE] ?? '')) ?: null,
                'unit' => trim((string) ($row[self::COL_UNIT] ?? '')) ?: null,
                'quantity' => max(0, (int) ($row[self::COL_QTY] ?? 0)),
                'cost' => $money($row[self::COL_COST] ?? 0),
                'price' => $money($row[self::COL_PRICE] ?? 0),
            ];
        }

        return $items;
    }

    /**
     * Minimal xlsx reader — an xlsx is a zip of XML, and the project has no
     * spreadsheet library installed.
     *
     * @return array<int,array<int,string>>
     */
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

    private function classify(string $name): string
    {
        foreach (self::RULES as [$pattern, $key]) {
            if (preg_match($pattern, $name)) {
                return $key;
            }
        }

        return self::FALLBACK;
    }

    private function wipeCatalogue(): void
    {
        // warehouse_inventory and stock_movements cascade from products; the
        // invoice_items link is SET NULL, so any existing invoice survives with
        // its product reference cleared rather than disappearing.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('warehouse_inventory')->delete();
        DB::table('stock_movements')->delete();
        DB::table('products')->delete();
        DB::table('categories')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /** @return array<string,int> category key => id */
    private function createCategories(): array
    {
        $ids = [];
        $order = 0;

        foreach (self::CATEGORIES as $key => [$nameAr, $nameEn, $icon]) {
            $category = Category::create([
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'slug' => $key,
                'icon' => $icon,
                'sort_order' => ++$order,
                'is_active' => true,
            ]);

            $ids[$key] = $category->id;
        }

        return $ids;
    }

    /**
     * The main warehouse is owned by WarehouseSeeder, so it is created there
     * and simply resolved here. Duplicating the logic is how the codebase ended
     * up with a warehouse whose name depended on which seeder happened to run.
     */
    private function ensureWarehouse(): Warehouse
    {
        $this->call(WarehouseSeeder::class);

        return Warehouse::findOrFail(WarehouseSeeder::mainWarehouseId());
    }

    /** @return array<int,array{id:int,quantity:int,cost:float}> */
    private function createProducts(array $items, array $categoryIds): array
    {
        $created = [];
        $usedSlugs = [];

        foreach ($items as $index => $item) {
            $key = $this->classify($item['name']);

            // Names repeat in the sheet, so slugs are made unique rather than
            // letting the second row collide with the first.
            $base = Str::slug($item['name'], '-', null) ?: 'item-' . ($index + 1);
            $slug = $base;
            $n = 1;
            while (isset($usedSlugs[$slug])) {
                $slug = $base . '-' . (++$n);
            }
            $usedSlugs[$slug] = true;

            $product = Product::create([
                'category_id' => $categoryIds[$key],
                'name_ar' => $item['name'],
                'slug' => $slug,
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
        }

        return $created;
    }

    private function stockProducts(array $created, int $warehouseId): void
    {
        $inventory = app(InventoryService::class);

        foreach ($created as $product) {
            if ($product['quantity'] <= 0) {
                continue;
            }

            $inventory->receive($product['id'], $product['quantity'], $warehouseId, [
                'key' => 'seed:catalog:product:' . $product['id'],
                'source' => 'opening_stock',
                'reason' => 'رصيد افتتاحي من ملف الوارد',
                'unit_cost' => $product['cost'],
            ]);
        }
    }

    /**
     * Products with no opening quantity still belong to the warehouse, so they
     * appear in its listings and can receive stock later.
     */
    private function linkRemaining(int $warehouseId): void
    {
        $unlinked = Product::doesntHave('inventory')->pluck('id');

        foreach ($unlinked as $productId) {
            WarehouseInventory::create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => 0,
                'available_quantity' => 0,
                'damaged_quantity' => 0,
                'quarantined_quantity' => 0,
                'reserved_quantity' => 0,
                'cost_basis' => WarehouseInventory::COST_BASIS_FIFO,
            ]);
        }
    }

    private function report(Warehouse $warehouse): void
    {
        if (!$this->command) {
            return;
        }

        $rows = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => [
                $c->name_ar,
                $c->products_count,
                number_format((int) Product::where('category_id', $c->id)->sum('stock_quantity')),
            ])
            ->all();

        $this->command->table(['الفئة', 'الأصناف', 'الوحدات'], $rows);

        $this->command->info(sprintf(
            'المستودع %s: %d منتج مرتبط، %s وحدة. بلا سعر بيع: %d.',
            $warehouse->name,
            WarehouseInventory::where('warehouse_id', $warehouse->id)->count(),
            number_format((int) WarehouseInventory::where('warehouse_id', $warehouse->id)->sum('quantity')),
            Product::whereNull('price')->orWhere('price', 0)->count()
        ));
    }
}
