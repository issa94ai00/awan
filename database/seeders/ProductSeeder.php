<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use ZipArchive;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = $this->loadProductsFromDailyXlsx(base_path('daily.xlsx'));

        if (empty($products)) {
            $this->command->error('daily.xlsx was not found or contained no valid product rows.');
            return;
        }

        foreach ($products as $product) {
            // Handle duplicate SKUs by appending a suffix if SKU already exists
            if ($product['sku'] && Product::where('sku', $product['sku'])->exists()) {
                $suffix = 1;
                $originalSku = $product['sku'];
                while (Product::where('sku', $product['sku'])->exists()) {
                    $product['sku'] = $originalSku . '-' . $suffix;
                    $suffix++;
                }
            }

            Product::updateOrCreate(
                ['slug' => $product['slug']],
                $product
            );
        }

        $this->linkProductsToMainWarehouse($products);

        $this->updateCategoryCounts($products);

        $this->command->info('Seeded products from daily.xlsx');
    }

    protected function loadProductsFromDailyXlsx(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->loadSharedStrings($zip);
        $rows = $this->loadSheetRows($zip, 'xl/worksheets/sheet1.xml', $sharedStrings);
        $zip->close();

        if (count($rows) < 2) {
            return [];
        }

        $products = [];

        $headerRowIndex = $this->findHeaderRowIndex($rows);
        $headerRow = $headerRowIndex !== null ? $rows[$headerRowIndex] : $rows[0];
        $columnMap = $this->mapHeaderColumns($headerRow);
        $startRow = ($headerRowIndex !== null ? $headerRowIndex + 1 : 3);

        for ($rowIndex = $startRow; $rowIndex < count($rows); $rowIndex++) {
            $row = $rows[$rowIndex];
            $nameAr = trim($row[$columnMap['name_ar']] ?? $row[0] ?? '');
            if ($nameAr === '') {
                continue;
            }

            $nameEn = trim($row[$columnMap['name_en']] ?? $row[0] ?? $nameAr);
            $sku = trim($row[$columnMap['sku']] ?? $row[1] ?? '');
            $unit = trim($row[$columnMap['unit']] ?? $row[2] ?? '');
            $quantity = $this->parseDecimal($row[$columnMap['quantity']] ?? $row[5] ?? '0');
            $costPrice = $this->parseDecimal($row[$columnMap['cost_price']] ?? $row[6] ?? '0');
            $price = $this->parseDecimal($row[$columnMap['price']] ?? $row[8] ?? '0');
            $slug = Str::slug($nameEn . ($sku ? ' ' . $sku : ''));
            $categoryId = $this->guessCategoryId($nameAr, $nameEn);

            $products[] = [
                'category_id' => $categoryId,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'slug' => $slug,
                'description_ar' => $nameAr,
                'description_en' => $nameEn,
                'price' => $price,
                'cost_price' => $costPrice,
                'stock_quantity' => (int) $quantity,
                'sku' => $sku ?: null,
                'unit' => $unit ?: null,
                'in_stock' => $quantity > 0 ? 1 : 0,
                'is_active' => 1,
                'show_price' => 1,
                'is_featured' => 0,
                'sort_order' => $rowIndex - $startRow,
            ];
        }

        return $products;
    }

    protected function findHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            if ($this->rowLooksLikeHeader($row)) {
                return $index;
            }
        }

        return null;
    }

    protected function rowLooksLikeHeader(array $row): bool
    {
        $candidates = [
            'الصنف',
            'نص الصنف',
            'الاسم',
            'الاسم بالإنجليزية',
            'name_ar',
            'name_en',
            'sku',
        ];

        foreach ($row as $cell) {
            $normalized = $this->normalizeHeader($cell);
            if (in_array($normalized, array_map([$this, 'normalizeHeader'], $candidates), true)) {
                return true;
            }
        }

        return false;
    }

    protected function mapHeaderColumns(array $headerRow): array
    {
        $mapping = [
            'name_ar' => ['الصنف', 'الاسم', 'name_ar', 'name', 'arabic name', 'الاسم بالعربية', 'الاسم بالعربي'],
            'name_en' => ['نص الصنف', 'الاسم بالإنجليزية', 'name_en', 'english name', 'english', 'الاسم بالانجليزية', 'الاسم بالانجليزي'],
            'sku' => ['sku', 'الكود', 'كود', 'code', 'itemcode', 'كود المنتج', 'الرقم'],
            'unit' => ['الوحدة', 'unit', 'unit_of_measurement', 'وحدة القياس'],
            'quantity' => ['الكمية', 'quantity', 'stock', 'qty', 'المخزون'],
            'cost_price' => ['التكلفة', 'سعر التكلفة', 'cost_price', 'cost', 'سعر الشراء'],
            'price' => ['السعر', 'price', 'selling price', 'سعر البيع'],
        ];

        $columnIndexes = array_fill_keys(array_keys($mapping), null);

        foreach ($headerRow as $index => $value) {
            $normalized = $this->normalizeHeader($value);

            foreach ($mapping as $field => $headers) {
                foreach ($headers as $header) {
                    if ($normalized === $this->normalizeHeader($header)) {
                        $columnIndexes[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        return $columnIndexes;
    }

    protected function normalizeHeader(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/[\s\x{FEFF}\x{00A0}]+/u', ' ', $value)));
    }

    protected function guessCategoryId(string $nameAr, string $nameEn): int
    {
        $slug = $this->guessCategorySlug($nameAr, $nameEn);
        $categoryId = $this->findCategoryIdBySlug($slug);

        if ($categoryId !== null) {
            return $categoryId;
        }

        return Category::where('slug', 'general-plumbing-supplies')->value('id')
            ?? Category::where('slug', 'plumbing-and-sanitary-materials')->value('id')
            ?? 11;
    }

    protected function guessCategorySlug(string $nameAr, string $nameEn): string
    {
        $text = mb_strtolower(trim($nameAr . ' ' . $nameEn));

        foreach ($this->categoryPatterns() as $slug => $patterns) {
            foreach ($patterns as $pattern) {
                if (mb_stripos($text, $pattern) !== false) {
                    return $slug;
                }
            }
        }

        return 'general-plumbing-supplies';
    }

    protected function categoryPatterns(): array
    {
        return [
            'hinges' => ['مفصلة', 'مفصلات', 'hinge', 'hinges'],
            'faucets-and-valves' => ['خلاط', 'حنفية', 'خلاطات', 'faucية', 'faucet', 'tap', 'mixer', 'valve', 'فولى'],
            'toilet-seats-accessories' => ['كرسي', 'غطاء', 'مرحاض', 'toilet', 'seat', 'سيت'],
            'bathroom-accessories' => ['اكسسوار حمام', 'حمام', 'accessory', 'اكسسوار'],
            'filters-and-drains' => ['مصفي', 'مصارف', 'مصرف', 'مصفاة', 'drain', 'filter', 'drainage'],
            'elbows-and-valves' => ['أوكرا', 'وصلة', 'وصلات', 'صمام', 'valve', 'elbow'],
            'pipes-and-fittings' => ['مواسير', 'أنابيب', 'fitting', 'pipe', 'pipes', 'أنبوب'],
            'sinks-and-basins' => ['حوض', 'مغسلة', 'باسين', 'sink', 'basin'],
            'tools-and-hardware' => ['عدد', 'أدوات', 'مطرقة', 'مفك', 'اسافين', 'tool', 'tools', 'wrench', 'drill', 'سكاكين'],
            'general-plumbing-supplies' => ['سباكة', 'تجهيزات', 'خراطيم', 'سيفون', 'قطع غيار', 'plumbing', 'supply', 'supplies'],
        ];
    }

    protected function findCategoryIdBySlug(string $slug): ?int
    {
        static $cache = [];

        if (array_key_exists($slug, $cache)) {
            return $cache[$slug];
        }

        $cache[$slug] = Category::where('slug', $slug)->value('id');

        return $cache[$slug];
    }

    protected function loadSharedStrings(ZipArchive $zip): array
    {
        $xmlContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlContent === false) {
            return [];
        }

        $xml = new \SimpleXMLElement($xmlContent);
        $strings = [];

        foreach ($xml->si as $si) {
            $text = '';

            if (isset($si->t)) {
                $text = (string) $si->t;
            } else {
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            $strings[] = $text;
        }

        return $strings;
    }

    protected function loadSheetRows(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $xmlContent = $zip->getFromName($sheetPath);
        if ($xmlContent === false) {
            return [];
        }

        $xml = new \SimpleXMLElement($xmlContent);
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $type = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's') {
                    $sharedIndex = intval($value);
                    $value = $sharedStrings[$sharedIndex] ?? $value;
                }

                $columnIndex = $this->columnIndex($reference);
                $cells[$columnIndex] = trim($value);
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    protected function columnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)\d+$/', $reference, $matches);
        $column = $matches[1] ?? '';
        $index = 0;

        foreach (str_split($column) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    protected function parseDecimal(string $value): float
    {
        $value = trim(str_replace(',', '.', $value));

        if ($value === '' || !is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    protected function updateCategoryCounts(array $products): void
    {
        $categoryIds = array_unique(array_column($products, 'category_id'));

        foreach ($categoryIds as $categoryId) {
            $category = Category::find($categoryId);
            if (! $category) {
                continue;
            }

            $category->update([
                'product_count' => Product::query()
                    ->where('category_id', $category->id)
                    ->where('is_active', 1)
                    ->count(),
            ]);
        }
    }

    protected function linkProductsToMainWarehouse(array $products): void
    {
        $warehouse = Warehouse::where('is_primary', true)->first();

        if (!$warehouse) {
            $this->command->warn('No primary warehouse found. Skipping warehouse inventory linking.');
            return;
        }

        foreach ($products as $product) {
            $productModel = Product::where('slug', $product['slug'])->first();

            if (!$productModel) {
                continue;
            }

            \App\Models\WarehouseInventory::updateOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $productModel->id,
                    'product_variant_id' => null,
                ],
                [
                    'quantity' => $product['stock_quantity'] ?? 0,
                    'reorder_point' => 5,
                    'safety_stock' => 2,
                ]
            );
        }

        $this->command->info('Linked products to main warehouse.');
    }
}
