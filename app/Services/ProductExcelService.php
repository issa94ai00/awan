<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;
use DOMDocument;
use DOMElement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class ProductExcelService
{
    /**
     * Ordered export columns: header => cell type ('string' | 'number').
     */
    private const EXPORT_COLUMNS = [
        'الكود' => 'string',
        'الاسم' => 'string',
        'الاسم بالإنجليزية' => 'string',
        'الفئة' => 'string',
        'السعر' => 'number',
        'سعر التكلفة' => 'number',
        'الكمية' => 'number',
        'الوحدة' => 'string',
        'العلامة التجارية' => 'string',
        'الموديل' => 'string',
        'الباركود' => 'string',
        'الوصف التفصيلي' => 'string',
        'الحالة' => 'string',
        'مميز' => 'string',
    ];

    /**
     * The stock sheet's columns.
     *
     * A stock sheet is a *count document*: it records what is physically on the
     * shelf and in what condition. That is the line the columns are drawn along.
     *
     *   editable    price, cost, the physical condition buckets, reorder point
     *   computed    reserved, and what is sellable — both derived from orders
     *               and from the buckets, so importing them would mean the sheet
     *               overwriting facts it does not own
     *
     * "الكمية المتاحة" used to head this sheet, carrying the `available_quantity`
     * column — the good-condition bucket, which does not subtract reserved. The
     * inventory panel prints the same words over a different figure (quantity
     * less reserved, damaged and quarantined), so the export and the screen
     * disagreed for every product with a reservation against it while insisting
     * they meant the same thing. Each number now has a name of its own.
     */
    private const INVENTORY_EXPORT_COLUMNS = [
        'المستودع' => 'string',
        'SKU' => 'string',
        'الاسم' => 'string',
        'الاسم بالإنجليزية' => 'string',
        'الباركود' => 'string',
        'سعر البيع' => 'number',
        'سعر التكلفة' => 'number',
        'الكمية الإجمالية' => 'number',
        'التالفة' => 'number',
        'المحتجزة' => 'number',
        'نقطة إعادة الترتيب' => 'number',
        // Everything past this point is written for the reader and ignored by
        // the importer — see IMPORT_IGNORED_FIELDS.
        'السليمة (محسوب)' => 'number',
        'المحجوز (محسوب)' => 'number',
        'المتاح للبيع (محسوب)' => 'number',
    ];

    /**
     * Recognised on import, deliberately not applied.
     *
     * Reserved is a hold the system places against real orders; a sheet that
     * could rewrite it would release or invent promises to customers. Sellable
     * and the good bucket are arithmetic over the other columns. Silently
     * dropping them is what made the old round trip lossy, so they are named
     * here and reported back to the caller instead.
     */
    private const IMPORT_IGNORED_FIELDS = [
        'reserved_quantity' => 'المحجوز',
        'sellable_quantity' => 'المتاح للبيع',
        'available_quantity' => 'السليمة',
    ];

    private const COLUMN_MAP = [
        'sku' => ['الكود', 'sku', 'code', 'itemcode', 'كود المنتج', 'رقم المنتج', 'المعرف'],
        'name_ar' => ['الاسم', 'name_ar', 'name', 'arabic name', 'الاسم بالعربية', 'الاسم بالعربي', 'الوصف', 'المنتج'],
        'name_en' => ['name_en', 'english name', 'english', 'الاسم بالإنجليزية', 'الاسم بالانجليزية', 'الاسم بالانجليزي'],
        'category' => ['الفئة', 'category', 'categories', 'category_name', 'التصنيف', 'القسم'],
        'price' => ['السعر', 'price', 'سعر البيع', 'selling price', 'المبلغ'],
        'cost_price' => ['سعر التكلفة', 'cost_price', 'التكلفة', 'cost', 'سعر الشراء'],
        // The total physically on the shelf, in every condition. Sheets exported
        // before the columns were disambiguated say "الكمية"; both are accepted
        // so an older file still imports as its author meant it.
        'stock_quantity' => ['الكمية الإجمالية', 'الكمية', 'المتبقي', 'المتبقي مخزون', 'سحب أيوب', 'stock', 'quantity', 'stock_quantity', 'qty', 'الرصيد', 'المخزون'],
        'warehouse' => ['المستودع', 'warehouse', 'warehouse_id', 'warehouse name', 'warehouse_name'],
        'damaged_quantity' => ['التالفة', 'damaged_quantity', 'damaged'],
        'quarantined_quantity' => ['المحتجزة', 'quarantined_quantity', 'quarantined'],
        'reorder_point' => ['نقطة إعادة الترتيب', 'reorder_point', 'reorder'],
        // Recognised so the importer can say it ignored them rather than let a
        // typed-in change vanish without a word. See IMPORT_IGNORED_FIELDS.
        'available_quantity' => ['السليمة (محسوب)', 'السليمة', 'الكمية المتاحة', 'available_quantity', 'available', 'available qty'],
        'reserved_quantity' => ['المحجوز (محسوب)', 'المحجوز', 'reserved_quantity', 'reserved'],
        'sellable_quantity' => ['المتاح للبيع (محسوب)', 'المتاح للبيع', 'sellable', 'sellable_quantity'],
        'unit' => ['الوحدة', 'unit', 'unit_of_measurement', 'وحدة القياس'],
        'brand' => ['العلامة التجارية', 'brand', 'الماركة', 'الشركة'],
        'model' => ['الموديل', 'model', 'الطراز'],
        'barcode' => ['الباركود', 'barcode', 'رقم الباركود'],
        'description_ar' => ['الوصف التفصيلي', 'description', 'description_ar', 'الوصف الكامل', 'details', 'التفاصيل'],
        'slug' => ['slug', 'الرابط', 'permalink'],
        'is_active' => ['الحالة', 'is_active', 'status', 'active', 'النشاط'],
        'is_featured' => ['مميز', 'is_featured', 'featured', 'المميز'],
    ];

    /**
     * Generate an xlsx document for the given products.
     */
    public function exportProducts(Collection $products): string
    {
        $rows = $products->map(function (Product $product) {
            return [
                'الكود' => $product->sku,
                'الاسم' => $product->name_ar,
                'الاسم بالإنجليزية' => $product->name_en,
                'الفئة' => $product->category ? ($product->category->name_ar ?: $product->category->name_en) : '',
                'السعر' => $product->price !== null ? (float) $product->price : '',
                'سعر التكلفة' => $product->cost_price !== null ? (float) $product->cost_price : '',
                'الكمية' => $product->stock_quantity !== null ? (int) $product->stock_quantity : 0,
                'الوحدة' => $product->unit,
                'العلامة التجارية' => $product->brand,
                'الموديل' => $product->model,
                'الباركود' => $product->barcode,
                'الوصف التفصيلي' => $product->description_ar,
                'الحالة' => $product->is_active ? 'نشط' : 'غير نشط',
                'مميز' => $product->is_featured ? 'نعم' : 'لا',
            ];
        })->all();

        return $this->buildXlsx(self::EXPORT_COLUMNS, $rows);
    }

    public function exportWarehouseInventory(Collection $inventory): string
    {
        $rows = $inventory->map(function (WarehouseInventory $item) {
            $nameEn = trim((string) ($item->product->name_en ?? ''));
            if ($nameEn === '') {
                $nameEn = trim((string) ($item->product->name_ar ?? ''));
            }

            $quantity = (int) ($item->quantity ?? 0);
            $reserved = (int) ($item->reserved_quantity ?? 0);
            $damaged = (int) ($item->damaged_quantity ?? 0);
            $quarantined = (int) ($item->quarantined_quantity ?? 0);

            return [
                'المستودع' => $item->warehouse->name ?? '',
                'SKU' => $item->product->sku ?? '',
                'الاسم' => $item->product->name_ar ?? '',
                'الاسم بالإنجليزية' => $nameEn,
                'الباركود' => $item->product->barcode ?? '',
                // Exported so the sheet can be edited and re-imported as a
                // price list as well as a stock count. Cost price rides along
                // because it drives the unit cost of any adjustment the import
                // books, and the valuation column on the inventory screen.
                'سعر البيع' => (float) ($item->product->price ?? 0),
                'سعر التكلفة' => (float) ($item->product->cost_price ?? 0),
                'الكمية الإجمالية' => $quantity,
                'التالفة' => $damaged,
                'المحتجزة' => $quarantined,
                'نقطة إعادة الترتيب' => (int) ($item->reorder_point ?? 0),

                /* -- computed, for reading only -- */

                'السليمة (محسوب)' => (int) ($item->available_quantity ?? 0),
                'المحجوز (محسوب)' => $reserved,
                // Read off the model rather than recomputed, so the sheet cannot
                // drift from the screen. Spelling the sum out here was already
                // one definition too many: it agreed with the panel only while
                // the condition buckets added up to `quantity`.
                'المتاح للبيع (محسوب)' => $item->available_stock,
            ];
        })->all();

        return $this->buildXlsx(self::INVENTORY_EXPORT_COLUMNS, $rows);
    }

    public function importStockFile(UploadedFile $file): array
    {
        $rows = $this->read($file->getRealPath());

        $result = [
            'products_created' => 0,
            'products_matched' => 0,
            'prices_updated' => 0,
            'inventory_rows' => 0,
            'inventory_created' => 0,
            'inventory_updated' => 0,
            // Columns present in the sheet that the importer will not apply.
            // Reported rather than dropped in silence: an operator who edits the
            // reserved column deserves to be told it did nothing, instead of
            // believing the change took and finding out later from a wrong
            // figure on a screen somewhere else.
            'ignored_columns' => $this->ignoredColumnsIn($rows),
            'errors' => [],
        ];

        foreach ($rows as $index => $rawRow) {
            // +1 for the header row, so the number matches what the operator
            // sees in the spreadsheet's own gutter when they go to fix it.
            $rowNumber = $index + 2;

            try {
                $data = $this->mapRow($rawRow);

                if (empty($data['warehouse'])) {
                    throw new \RuntimeException('عمود المستودع مطلوب.');
                }

                if ($data['stock_quantity'] === null) {
                    throw new \RuntimeException('عمود الكمية الإجمالية مطلوب.');
                }

                $product = $this->findOrCreateProduct($data);
                if ($product->wasRecentlyCreated) {
                    $result['products_created']++;
                } else {
                    $result['products_matched']++;

                    // Pricing columns only reached brand-new products before, so
                    // re-importing an edited export silently discarded every
                    // price change on products that already existed.
                    if ($this->applyPricing($product, $data)) {
                        $result['prices_updated']++;
                    }
                }

                $quantity = (int) $data['stock_quantity'];
                // A missing reorder-point column leaves the existing setting
                // alone rather than resetting every product's trigger to zero,
                // which would silence the restock screen company-wide.
                $reorderPoint = $data['reorder_point'];
                $warehouse = $this->resolveWarehouse($data['warehouse']);

                // Keyed on warehouse + product, so the same product listed under
                // a second warehouse gets its own stock row rather than moving
                // the existing one.
                $existing = WarehouseInventory::where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->whereNull('product_variant_id')
                    ->first();

                $this->setWarehouseStock(
                    $warehouse,
                    $product,
                    $quantity,
                    $reorderPoint ?? (int) ($existing->reorder_point ?? 0),
                    $data['damaged_quantity'],
                    $data['quarantined_quantity'],
                );

                $existed = $existing !== null;

                $result['inventory_rows']++;
                $existed ? $result['inventory_updated']++ : $result['inventory_created']++;
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Which computed columns the sheet carries that the importer will not apply.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,string> Arabic labels, ready to show
     */
    private function ignoredColumnsIn(array $rows): array
    {
        $headers = array_keys($rows[0] ?? []);
        $present = [];

        foreach ($headers as $header) {
            $field = $this->fieldForHeader($header);

            if ($field !== null && isset(self::IMPORT_IGNORED_FIELDS[$field])) {
                $present[$field] = self::IMPORT_IGNORED_FIELDS[$field];
            }
        }

        return array_values($present);
    }

    /**
     * Read an xlsx file and upsert the contained products.
     *
     * @return array{created: int, updated: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function importFile(UploadedFile $file): array
    {
        $rows = $this->read($file->getRealPath());

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($rows as $index => $rawRow) {
            $rowNumber = $index + 1;
            try {
                $data = $this->mapRow($rawRow);

                $nameAr = $data['name_ar'] ?: $data['name_en'];
                if (! $nameAr) {
                    $result['skipped']++;

                    continue;
                }

                $values = [];
                foreach (['name_ar', 'name_en', 'description_ar', 'brand', 'model', 'unit', 'barcode', 'price', 'cost_price'] as $field) {
                    if ($data[$field] !== null) {
                        $values[$field] = $data[$field];
                    }
                }
                if ($data['is_active'] !== null) {
                    $values['is_active'] = $data['is_active'];
                }
                if ($data['is_featured'] !== null) {
                    $values['is_featured'] = $data['is_featured'];
                }
                if ($data['category'] !== null) {
                    $categoryId = $this->resolveCategory($data['category']);
                    if ($categoryId) {
                        $values['category_id'] = $categoryId;
                    }
                }
                if ($data['stock_quantity'] !== null) {
                    $values['stock_quantity'] = $data['stock_quantity'];
                    $values['in_stock'] = $data['stock_quantity'] > 0;
                }
                if ($data['slug'] !== null) {
                    $values['slug'] = $this->uniqueSlug($data['slug']);
                }

                if ($data['sku']) {
                    $product = Product::where('sku', $data['sku'])->first();
                    if ($product) {
                        $this->applyUniqueSlug($product, $values);
                        $product->update($values);
                        $result['updated']++;

                        continue;
                    }
                } elseif (! empty($data['name_ar'])) {
                    $product = Product::where('name_ar', $data['name_ar'])->first();
                    if ($product) {
                        $this->applyUniqueSlug($product, $values);
                        $product->update($values);
                        $result['updated']++;

                        continue;
                    }
                }

                if (empty($values['slug'])) {
                    $values['slug'] = $this->uniqueSlug($nameAr, $data['name_en']);
                }
                if ($data['sku']) {
                    $values['sku'] = $data['sku'];
                }
                Product::create($values);
                $result['created']++;
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Import a multi-sheet menu file into warehouses.
     *
     * Each worksheet becomes a warehouse named after the sheet; each row is a
     * product that gets linked to that warehouse. Products are matched by sku
     * (الكود) first and by name (الوصف) second; unknown products are created.
     * The per-row quantity lands in the warehouse's inventory row.
     *
     * @return array{
     *     sheets: int,
     *     warehouses_created: int,
     *     products_created: int,
     *     products_updated: int,
     *     products_matched: int,
     *     inventory_rows: int,
     *     errors: array<int, array{sheet: string, row: int, message: string}>
     * }
     */
    public function importMenuFile(string $path): array
    {
        $sheets = $this->readAllWorksheets($path);

        $result = [
            'sheets' => 0,
            'warehouses_created' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'products_matched' => 0,
            'inventory_rows' => 0,
            'errors' => [],
        ];

        foreach ($sheets as $sheetName => $rows) {
            $warehouse = $this->resolveWarehouse($sheetName);
            $result['sheets']++;

            if ($warehouse->wasRecentlyCreated) {
                $result['warehouses_created']++;
            }

            foreach ($rows as $index => $rawRow) {
                $rowNumber = $index + 1;

                try {
                    $data = $this->mapRow($rawRow);

                    $nameAr = $data['name_ar'] ?: $data['name_en'];
                    if (! $nameAr) {
                        continue;
                    }

                    $product = $this->findOrCreateProduct($data);
                    if ($product->wasRecentlyCreated) {
                        $result['products_created']++;
                    } else {
                        $result['products_matched']++;
                    }

                    $this->setWarehouseStock($warehouse, $product, $data['stock_quantity'] ?? 0);
                    $result['inventory_rows']++;
                } catch (\Throwable $e) {
                    $result['errors'][] = [
                        'sheet' => $sheetName,
                        'row' => $rowNumber,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Resolve a warehouse by its name, creating it if missing.
     */
    /**
     * Copies the pricing columns onto an existing product.
     *
     * @return bool whether anything actually changed
     */
    private function applyPricing(Product $product, array $data): bool
    {
        $changed = false;

        foreach (['price', 'cost_price'] as $field) {
            if ($data[$field] === null) {
                continue;
            }

            if ((float) $product->{$field} !== (float) $data[$field]) {
                $product->{$field} = $data[$field];
                $changed = true;
            }
        }

        if ($changed) {
            // A product with a real price should show it; the importer is the
            // only place that price arrives for most of the catalogue.
            if ((float) $product->price > 0) {
                $product->show_price = true;
            }
            $product->save();
        }

        return $changed;
    }

    private function resolveWarehouse(string $sheetName): Warehouse
    {
        $sheetName = trim($sheetName);

        if (is_numeric($sheetName)) {
            $warehouse = Warehouse::find((int) $sheetName);
            if ($warehouse) {
                return $warehouse;
            }
        }

        $warehouse = Warehouse::where('name', $sheetName)->first();
        if ($warehouse) {
            return $warehouse;
        }

        // `code` is NOT NULL with no default, so creating a warehouse without
        // one threw a database error and the whole row failed — which is what
        // happened whenever a sheet named a warehouse that did not exist yet.
        return Warehouse::create([
            'name' => $sheetName,
            'code' => $this->uniqueWarehouseCode(),
            'is_active' => true,
            'location_type' => Warehouse::TYPE_WAREHOUSE,
        ]);
    }

    private function uniqueWarehouseCode(): string
    {
        $next = (int) (Warehouse::max('id') ?? 0) + 1;

        do {
            $code = 'WH-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (Warehouse::where('code', $code)->exists());

        return $code;
    }

    /**
     * Match an existing product by sku then name; create it when missing.
     */
    private function findOrCreateProduct(array $data): Product
    {
        if (! empty($data['sku'])) {
            $product = Product::where('sku', $data['sku'])->first();
            if ($product) {
                return $product;
            }
        }

        if (! empty($data['name_ar'])) {
            $product = Product::where('name_ar', $data['name_ar'])->first();
            if ($product) {
                return $product;
            }
        }

        $nameAr = $data['name_ar'] ?: $data['name_en'];

        $values = [
            'name_ar' => $nameAr,
            'name_en' => $data['name_en'],
            'is_active' => true,
            'slug' => $this->uniqueSlug($nameAr, $data['name_en']),
        ];

        foreach (['sku', 'unit', 'price', 'cost_price', 'barcode'] as $field) {
            if ($data[$field] !== null) {
                $values[$field] = $data[$field];
            }
        }

        return Product::create($values);
    }

    /**
     * Applies a counted quantity to one product in one warehouse.
     *
     * The change goes through InventoryService rather than being written
     * straight into warehouse_inventory. Writing the row directly kept the
     * warehouse balance right but left `products.stock_quantity` frozen and
     * produced no movement record, so an imported sheet silently pulled the
     * product totals out of step with the warehouses they are meant to sum.
     *
     * A sheet states what is on the shelf, so the difference is booked as a
     * stock-count adjustment — which is also what makes it auditable.
     *
     * ## The buckets
     *
     * `quantity` is not a figure of its own: it is the sum of the condition
     * buckets, and everything downstream depends on that holding. The
     * reservation check reads `available_quantity - reserved_quantity`; the
     * field app's sourcing and restock screens read the same. Adjusting only the
     * total — which is what this did — pushed the entire difference into the
     * good bucket while damaged and quarantined stayed where they were, so a
     * sheet reporting three damaged units *added* three sellable ones.
     *
     * Each bucket is therefore moved to its own counted figure, and the total
     * follows from them. Reserved is never touched here: it is a hold against
     * real orders, and no count document owns it.
     *
     * @param  int|null  $damaged  null leaves the bucket as it is
     * @param  int|null  $quarantined  null leaves the bucket as it is
     */
    private function setWarehouseStock(
        Warehouse $warehouse,
        Product $product,
        int $quantity,
        int $reorderPoint = 0,
        ?int $damaged = null,
        ?int $quarantined = null,
    ): void {
        $quantity = max(0, $quantity);
        $reorderPoint = max(0, $reorderPoint);

        $row = WarehouseInventory::firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => 0,
                'available_quantity' => 0,
                'damaged_quantity' => 0,
                'quarantined_quantity' => 0,
                'reserved_quantity' => 0,
                'safety_stock' => 0,
                'cost_basis' => WarehouseInventory::COST_BASIS_FIFO,
            ]
        );

        // Planning fields are plain settings, not stock, so they are just set.
        $row->reorder_point = $reorderPoint;
        $row->save();

        // A column the sheet left out means "unchanged", not "zero". Wiping a
        // damaged count because the operator exported a narrower view would
        // quietly return broken goods to sale.
        $targetDamaged = max(0, $damaged ?? (int) $row->damaged_quantity);
        $targetQuarantined = max(0, $quarantined ?? (int) $row->quarantined_quantity);

        $unusable = $targetDamaged + $targetQuarantined;

        if ($unusable > $quantity) {
            throw new \RuntimeException(sprintf(
                'التالفة (%d) والمحتجزة (%d) معاً تتجاوزان الكمية الإجمالية (%d).',
                $targetDamaged,
                $targetQuarantined,
                $quantity
            ));
        }

        // Whatever is left after the unusable stock is the good bucket.
        $targetAvailable = $quantity - $unusable;

        $reserved = (int) $row->reserved_quantity;
        if ($targetAvailable < $reserved) {
            throw new \RuntimeException(sprintf(
                'الكمية السليمة بعد الجرد (%d) أقل من المحجوز لطلبات قائمة (%d). عالج الطلبات أولاً أو صحّح الجرد.',
                $targetAvailable,
                $reserved
            ));
        }

        $inventory = app(InventoryService::class);

        // One adjustment per bucket, each writing its own movement — a count
        // that finds damage is a real inventory event and belongs in the log,
        // not folded invisibly into a single net number.
        $moves = [
            InventoryService::CONDITION_AVAILABLE => $targetAvailable - (int) $row->available_quantity,
            InventoryService::CONDITION_DAMAGED => $targetDamaged - (int) $row->damaged_quantity,
            InventoryService::CONDITION_QUARANTINED => $targetQuarantined - (int) $row->quarantined_quantity,
        ];

        foreach ($moves as $condition => $difference) {
            if ($difference === 0) {
                continue;
            }

            $inventory->adjust(
                $product->id,
                $difference,
                $warehouse->id,
                [
                    'condition' => $condition,
                    'source' => 'stock_import',
                    'reason' => 'جرد مستورد من ملف',
                    'reference' => $warehouse->name,
                    'unit_cost' => (float) ($product->cost_price ?? 0),
                ]
            );
        }
    }

    /**
     * Read an xlsx file and return rows keyed by their header values.
     */
    public function read(string $path): array
    {
        if (! file_exists($path)) {
            throw new \RuntimeException('The file does not exist.');
        }

        if (filesize($path) === 0) {
            throw new \RuntimeException('The file is empty.');
        }

        $sheets = $this->readAllWorksheets($path);

        if ($sheets === []) {
            throw new \RuntimeException('The file does not contain a worksheet or the worksheet is empty. Please ensure the file has at least one sheet with data.');
        }

        return reset($sheets);
    }

    /**
     * Read every worksheet in the file, keyed by the sheet name. This is what
     * a multi-sheet menu file needs — each sheet is treated as a separate
     * document (e.g. one warehouse per sheet).
     *
     * @return array<string, array<int, array<string, string>>> sheetName => rows
     */
    public function readAllWorksheets(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Unable to open the file as an Excel (xlsx) file.');
        }

        try {
            $shared = $this->readSharedStrings($zip);
            $sheets = [];

            foreach ($this->worksheetTargets($zip) as $name => $target) {
                $xml = $zip->getFromName($target);
                if ($xml === false) {
                    continue;
                }

                $rows = $this->parseWorksheetRows($xml, $shared);
                if ($rows !== []) {
                    $sheets[$name] = $rows;
                }
            }

            if ($sheets === []) {
                foreach ($this->scanWorksheetFiles($zip) as $name => $target) {
                    $xml = $zip->getFromName($target);
                    if ($xml === false) {
                        continue;
                    }

                    $rows = $this->parseWorksheetRows($xml, $shared);
                    if ($rows !== []) {
                        $sheets[$name] = $rows;
                    }
                }
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }

    /**
     * Resolve every worksheet part to its path inside the package.
     *
     * @return array<string, string> sheet name => archive path
     */
    private function worksheetTargets(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        // Fallback: try to find any worksheet file directly
        if ($workbookXml === false || $relsXml === false) {
            $targets = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, 'xl/worksheets/sheet') === 0 && strpos($name, '.xml') !== false) {
                    $sheetName = 'Sheet'.(count($targets) + 1);
                    $targets[$sheetName] = $name;
                }
            }

            return $targets ?: ['Sheet1' => 'xl/worksheets/sheet1.xml'];
        }

        $relationships = [];
        $rels = new DOMDocument;
        if (@$rels->loadXML($relsXml)) {
            foreach ($rels->getElementsByTagNameNS(
                'http://schemas.openxmlformats.org/package/2006/relationships',
                'Relationship'
            ) as $relationship) {
                $relationships[$relationship->getAttribute('Id')] = $relationship->getAttribute('Target');
            }
        }

        $targets = [];
        $workbook = new DOMDocument;
        if (@$workbook->loadXML($workbookXml)) {
            foreach ($workbook->getElementsByTagNameNS(
                'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
                'sheet'
            ) as $sheet) {
                $name = $sheet->getAttribute('name');
                $relationshipId = $sheet->getAttributeNS(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                    'id'
                );

                $target = $relationships[$relationshipId] ?? null;
                if ($target === null) {
                    continue;
                }
                if (strpos($target, 'worksheets/') === false) {
                    $target = 'xl/'.ltrim($target, '/');
                }

                $targets[$name] = $target;
            }
        }

        // If no sheets found via workbook.xml, try direct search
        if (empty($targets)) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, 'xl/worksheets/sheet') === 0 && strpos($name, '.xml') !== false) {
                    $sheetName = 'Sheet'.(count($targets) + 1);
                    $targets[$sheetName] = $name;
                }
            }
        }

        return $targets ?: ['Sheet1' => 'xl/worksheets/sheet1.xml'];
    }

    /**
     * Parse a worksheet part into rows keyed by their header values.
     *
     * @param  array<int, string>  $shared
     * @return array<int, array<string, string>>
     */
    private function parseWorksheetRows(string $sheetXml, array $shared): array
    {
        $rows = [];

        $dom = new DOMDocument;
        if (! @$dom->loadXML($sheetXml)) {
            return $rows;
        }

        $headers = [];
        foreach ($dom->getElementsByTagName('row') as $rowElement) {
            $cells = [];
            $columnCounter = 0;
            foreach ($rowElement->getElementsByTagName('c') as $cellElement) {
                $ref = $cellElement->getAttribute('r');
                $index = $ref !== '' ? $this->colToIndex($ref) : $columnCounter;
                $cells[$index] = $this->cellValue($cellElement, $shared);
                $columnCounter++;
            }

            if (! $cells) {
                continue;
            }

            $maxColumn = max(array_keys($cells));

            if (! $headers) {
                // Use the first non-empty row as the header row.
                $hasValue = false;
                foreach ($cells as $cellValue) {
                    if (trim((string) $cellValue) !== '') {
                        $hasValue = true;
                        break;
                    }
                }
                if (! $hasValue) {
                    continue;
                }
                for ($i = 0; $i <= $maxColumn; $i++) {
                    $headers[$i] = trim((string) ($cells[$i] ?? ''));
                }

                continue;
            }

            $row = [];
            $isEmpty = true;
            for ($i = 0; $i <= $maxColumn; $i++) {
                $header = $headers[$i] ?? '';
                if ($header === '') {
                    continue;
                }
                $value = trim((string) ($cells[$i] ?? ''));
                $row[$header] = $value;
                if ($value !== '') {
                    $isEmpty = false;
                }
            }

            if (! $isEmpty) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Build an xlsx binary document.
     *
     * @param  array<string, string>  $columns  header => cell type
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function buildXlsx(array $columns, array $rows): string
    {
        $files = [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelsXml(),
            'xl/worksheets/sheet1.xml' => $this->sheetXml($columns, $rows),
            'xl/styles.xml' => $this->stylesXml(),
        ];

        $tmpPath = tempnam(sys_get_temp_dir(), 'products');

        $zip = new ZipArchive;
        if ($zip->open($tmpPath, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the Excel file.');
        }

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $binary = file_get_contents($tmpPath);
        @unlink($tmpPath);

        return $binary;
    }

    /**
     * Build the worksheet XML.
     *
     * @param  array<string, string>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function sheetXml(array $columns, array $rows): string
    {
        $xml = '<sheetData>';

        $xml .= '<row r="1">';
        $index = 1;
        foreach (array_keys($columns) as $header) {
            // The reference has to carry the row number ("A1", not "A"), or
            // every row declares the same cells and Excel offers to repair the
            // file before opening it.
            $xml .= $this->cellXml($this->colLetter($index).'1', $header, 'string');
            $index++;
        }
        $xml .= '</row>';

        $rowNumber = 2;
        foreach ($rows as $row) {
            $xml .= '<row r="'.$rowNumber.'">';
            $index = 1;
            foreach ($columns as $header => $type) {
                $value = $row[$header] ?? '';
                $xml .= $this->cellXml($this->colLetter($index).$rowNumber, $value, $type);
                $index++;
            }
            $xml .= '</row>';
            $rowNumber++;
        }

        $xml .= '</sheetData>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .$xml
            .'</worksheet>';
    }

    /**
     * Build a single cell XML element.
     */
    private function cellXml(string $reference, mixed $value, string $type): string
    {
        if ($type === 'number' && $value !== '' && is_numeric($value)) {
            return '<c r="'.$reference.'"><v>'.$value.'</v></c>';
        }

        $text = $this->escapeXml((string) $value);

        return '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
    }

    /**
     * Map an imported row into normalized product data.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function mapRow(array $raw): array
    {
        $data = [
            'sku' => null,
            'name_ar' => null,
            'name_en' => null,
            'category' => null,
            'price' => null,
            'cost_price' => null,
            'stock_quantity' => null,
            'unit' => null,
            'brand' => null,
            'model' => null,
            'barcode' => null,
            'description_ar' => null,
            'slug' => null,
            'is_active' => null,
            'is_featured' => null,
            // Stock-sheet fields. These were absent from this list, so they
            // reached the importer through the catch-all branch below as raw
            // strings — untyped, and impossible to tell apart from "the column
            // was not in the sheet at all", which is the distinction the whole
            // "leave it as it is" behaviour rests on.
            'warehouse' => null,
            'damaged_quantity' => null,
            'quarantined_quantity' => null,
            'reorder_point' => null,
            'available_quantity' => null,
            'reserved_quantity' => null,
            'sellable_quantity' => null,
        ];

        foreach ($raw as $header => $value) {
            $field = $this->fieldForHeader($header);
            if ($field === null) {
                continue;
            }

            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            switch ($field) {
                case 'price':
                case 'cost_price':
                    $data[$field] = $this->toNumber($value);
                    break;
                case 'stock_quantity':
                case 'damaged_quantity':
                case 'quarantined_quantity':
                case 'reorder_point':
                case 'available_quantity':
                case 'reserved_quantity':
                case 'sellable_quantity':
                    $data[$field] = max(0, (int) $this->toNumber($value));
                    break;
                case 'is_active':
                    $data[$field] = $this->toBool($value, true);
                    break;
                case 'is_featured':
                    $data[$field] = $this->toBool($value, false);
                    break;
                case 'category':
                    $data['category'] = $value;
                    break;
                default:
                    $data[$field] = $value;
                    break;
            }
        }

        return $data;
    }

    /**
     * Resolve a category reference (id, name, or slug) to a category id.
     */
    private function resolveCategory(string $value): ?int
    {
        if (is_numeric($value)) {
            $category = Category::find((int) $value);
            if ($category) {
                return $category->id;
            }
        }

        $category = Category::where('name_ar', $value)
            ->orWhere('name_en', $value)
            ->orWhere('slug', $value)
            ->first();

        return $category ? $category->id : null;
    }

    /**
     * Ensure a unique slug for an existing product being updated.
     *
     * @param  array<string, mixed>  $values
     */
    private function applyUniqueSlug(Product $product, array &$values): void
    {
        if (isset($values['slug']) && $values['slug'] !== $product->slug) {
            $values['slug'] = $this->uniqueSlug($values['slug'], null, $product->id);
        } elseif (! isset($values['slug'])) {
            unset($values['slug']);
        }
    }

    /**
     * Generate a unique slug based on an English name (or fallback).
     */
    private function uniqueSlug(string $base, ?string $nameEn = null, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? Str::slug($base) : '';
        if ($slug === '' && $nameEn !== null && $nameEn !== '') {
            $slug = Str::slug($nameEn);
        }
        if ($slug === '') {
            $slug = 'product-'.substr(md5($base.uniqid('', true)), 0, 10);
        }

        $candidate = $slug;
        $counter = 2;
        while (Product::where('slug', $candidate)
            ->where('id', '!=', $ignoreId ?? 0)
            ->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * Map a header string to an internal field name, or null if unknown.
     */
    private function fieldForHeader(string $header): ?string
    {
        $normalized = $this->normalizeKey($header);

        foreach (self::COLUMN_MAP as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalized === $this->normalizeKey($alias)) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function normalizeKey(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['ك', 'ک', 'ڪ', 'ي', 'ی', 'ى'], ['ك', 'ك', 'ك', 'ي', 'ي', 'ي'], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = mb_strtolower($value, 'UTF-8');

        return $value;
    }

    private function toBool(string $value, bool $default): bool
    {
        $value = $this->normalizeKey($value);

        if (in_array($value, ['نشط', 'نعم', 'متاح', '1', 'true', 'yes', 'active', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['غير نشط', 'لا', 'غير متاح', '0', 'false', 'no', 'inactive', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function toNumber(string $value): float
    {
        $value = str_replace(['٬', ' '], '', $value);
        $value = preg_replace('/[^\d.,\-]/', '', $value) ?? '';
        $value = trim($value, '.');
        if ($value === '' || $value === '-') {
            return 0.0;
        }

        // Thousands separators: "1,200" / "12,345,678"
        if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $value)) {
            $value = str_replace(',', '', $value);
        } elseif (preg_match('/^-?\d+,\d+$/', $value)) {
            // Single comma followed by non-triplet digits -> decimal separator ("12,5")
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function cellValue(DOMElement $cell, array $shared): string
    {
        $type = $cell->getAttribute('t');

        if ($type === 's') {
            $indexNode = $cell->getElementsByTagName('v')->item(0);
            $index = $indexNode ? (int) $indexNode->textContent : 0;

            return $shared[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            $value = '';
            foreach ($cell->getElementsByTagName('t') as $textNode) {
                $value .= $textNode->textContent;
            }

            return $value;
        }

        $valueNode = $cell->getElementsByTagName('v')->item(0);

        return $valueNode ? $valueNode->textContent : '';
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $shared = [];
        $dom = new DOMDocument;
        if (@$dom->loadXML($xml)) {
            foreach ($dom->getElementsByTagName('si') as $item) {
                $text = '';
                foreach ($item->getElementsByTagName('t') as $textNode) {
                    $text .= $textNode->textContent;
                }
                $shared[] = $text;
            }
        }

        return $shared;
    }

    private function colToIndex(string $reference): int
    {
        $column = '';
        $length = strlen($reference);
        for ($i = 0; $i < $length; $i++) {
            if (ctype_alpha($reference[$i])) {
                $column .= $reference[$i];
            } else {
                break;
            }
        }

        if ($column === '') {
            return 0;
        }

        $index = 0;
        $length = strlen($column);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function colLetter(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }

    private function scanWorksheetFiles(ZipArchive $zip): array
    {
        $targets = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            if (preg_match('#^xl/worksheets/([^/]+\.xml)$#', $name, $matches)) {
                $sheetName = $matches[1];
                $targets[$sheetName] = $name;
            }
        }

        return $targets;
    }

    private function normalizeWorksheetPath(string $target): string
    {
        $target = ltrim($target, '/');
        if (strpos($target, 'xl/') === 0) {
            $target = substr($target, 3);
        }

        $segments = explode('/', $target);
        $resolved = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($resolved);

                continue;
            }
            $resolved[] = $segment;
        }

        $path = implode('/', $resolved);
        if (strpos($path, 'worksheets/') === 0) {
            return 'xl/'.$path;
        }

        return 'xl/'.$path;
    }

    private function escapeXml(string $value): string
    {
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? $value;

        return str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            $value
        );
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Products" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
