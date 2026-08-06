<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
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
     * Accepted header aliases (after normalization) mapped to an internal field.
     */
    private const COLUMN_MAP = [
        'sku' => ['الكود', 'sku', 'code', 'itemcode', 'كود المنتج', 'رقم المنتج', 'المعرف'],
        'name_ar' => ['الاسم', 'name_ar', 'name', 'arabic name', 'الاسم بالعربية', 'الاسم بالعربي', 'الوصف', 'المنتج'],
        'name_en' => ['name_en', 'english name', 'english', 'الاسم بالإنجليزية', 'الاسم بالانجليزية', 'الاسم بالانجليزي'],
        'category' => ['الفئة', 'category', 'categories', 'category_name', 'التصنيف', 'القسم'],
        'price' => ['السعر', 'price', 'سعر البيع', 'selling price', 'المبلغ'],
        'cost_price' => ['سعر التكلفة', 'cost_price', 'التكلفة', 'cost', 'سعر الشراء'],
        'stock_quantity' => ['الكمية', 'المتبقي', 'stock', 'quantity', 'stock_quantity', 'qty', 'الرصيد', 'المخزون'],
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
     * Read an xlsx file and return rows keyed by their header values.
     */
    public function read(string $path): array
    {
        $rows = [];

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Unable to open the file as an Excel (xlsx) file.');
        }

        try {
            $shared = $this->readSharedStrings($zip);
            $sheetXml = $this->readFirstWorksheet($zip);

            if ($sheetXml === '') {
                throw new \RuntimeException('The file does not contain a worksheet.');
            }

            $dom = new DOMDocument();
            if (! @$dom->loadXML($sheetXml)) {
                throw new \RuntimeException('The worksheet could not be parsed.');
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
        } finally {
            $zip->close();
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

        $zip = new ZipArchive();
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
            $xml .= $this->cellXml($this->colLetter($index), $header, 'string');
            $index++;
        }
        $xml .= '</row>';

        $rowNumber = 2;
        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNumber . '">';
            $index = 1;
            foreach ($columns as $header => $type) {
                $value = $row[$header] ?? '';
                $xml .= $this->cellXml($this->colLetter($index), $value, $type);
                $index++;
            }
            $xml .= '</row>';
            $rowNumber++;
        }

        $xml .= '</sheetData>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . $xml
            . '</worksheet>';
    }

    /**
     * Build a single cell XML element.
     */
    private function cellXml(string $reference, mixed $value, string $type): string
    {
        if ($type === 'number' && $value !== '' && is_numeric($value)) {
            return '<c r="' . $reference . '"><v>' . $value . '</v></c>';
        }

        $text = $this->escapeXml((string) $value);

        return '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
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
            $slug = 'product-' . substr(md5($base . uniqid('', true)), 0, 10);
        }

        $candidate = $slug;
        $counter = 2;
        while (Product::where('slug', $candidate)
            ->where('id', '!=', $ignoreId ?? 0)
            ->exists()) {
            $candidate = $slug . '-' . $counter;
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
        $dom = new DOMDocument();
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

    private function readFirstWorksheet(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml !== false && $relsXml !== false) {
            $workbook = new DOMDocument();
            if (@$workbook->loadXML($workbookXml)) {
                $sheet = $workbook->getElementsByTagNameNS(
                    'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
                    'sheet'
                )->item(0);

                if ($sheet) {
                    $relationshipId = $sheet->getAttributeNS(
                        'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                        'id'
                    );

                    $rels = new DOMDocument();
                    if (@$rels->loadXML($relsXml)) {
                        foreach ($rels->getElementsByTagNameNS(
                            'http://schemas.openxmlformats.org/package/2006/relationships',
                            'Relationship'
                        ) as $relationship) {
                            if ($relationship->getAttribute('Id') === $relationshipId) {
                                $target = $relationship->getAttribute('Target');
                                if (strpos($target, 'worksheets/') === false) {
                                    $target = 'xl/' . ltrim($target, '/');
                                }
                                $xml = $zip->getFromName($target);
                                if ($xml !== false) {
                                    return $xml;
                                }
                            }
                        }
                    }
                }
            }
        }

        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');

        return $xml === false ? '' : $xml;
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
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intdiv($index, 26);
        }

        return $letters;
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
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Products" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
