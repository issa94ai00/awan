<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Support\ProductIdentifiers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ImportAyoubInventory extends Command
{
    protected $signature = 'inventory:import-ayoub
                            {--file=public/ayoub.xlsx : Path to the ayoub.xlsx file}
                            {--warehouse=3 : Warehouse ID to receive stock into}
                            {--dry-run : Simulate the import without changing database}
                            {--force : Force the operation without confirmation}';

    protected $description = 'Read ayoub.xlsx to set inventory data for Ayoub warehouse (wh-ayoub)';

    /**
     * Curated mapping for specific items where Excel description is an abbreviated code or variation.
     */
    private const CURATED_MAPPINGS = [
        13 => 2153,  // سكر مجوز سوبر (2.75) -> سكر مجوز سوبر طبة كبيرة
        14 => 258,   // سكر مجوز صيني (2.25) -> سكر مجوز صيني فتحة جانبية
        15 => 2151,  // سكر زاوية سايروس ربع برمة 1/2 (1.5) -> سكر زاوية ماسورة 1/2 انش سايروس نص برمه
        17 => 246,   // سكر زاوية MHB 3/8 (1.2) -> سكر اعمى 1/2ب3/8 MHB
        18 => 282,   // كرت شطاف سايروس (2.25) -> شطاف كروم سايروس
        23 => 2263,  // بربيش حمام سيليكون فضي (1.3) -> بربيش حمام سيليكون لون فضي تقيل
        27 => 129,   // بربيش حمام راصور رمادي 150cm (0.9) -> بربيش راصور حمام 150 سم
        28 => 2331,  // بربيش دوش كروم 150cm (1.35) -> بربيش حمام كروم تقيل
        29 => 318,   // كرت شطاف مع سكر مجوز (6.75) -> كرت شطاف سكر مجوز
        33 => 2086,  // كباسة شطاف م غروهي (1.35) -> كباسة شطاف موديل غروهي
        35 => 387,   // مصفاية بلاط م أمريكي 15*15 (0.83) -> مصفاية 15*15 مديل امريكي
        38 => 334,   // مصفاية بلاطة F.F ازرق (2.6) -> مصفاية بلاطة f f زرقة 15 c
        39 => 335,   // مصفاية بلاطة F.F أخضر (1.9) -> مصفاية بلاطة ff خضرا 15 c
        42 => 2164,  // مصفاية بلاطة طويل F.F 60cm (11.5) -> مصفاية بلاطة 8 سم بطول 60 سم
        43 => 2098,  // ريكار 15*15 فيديكس (3.0) -> مصفاية 15سم فيديكس ثقيلة
        55 => 2084,  // كرت دوش وسط سماعة كبير MHB (3.015) -> كرت دوش اسود mhb سماعة كبيرة
        60 => 5277,  // كرت دوش خفيف (1.35) -> كرت دوش خفيف
        68 => 384,   // وصلة بولاد 100cm (0.5) -> وصلة بولاد 100 سم عادي
        79 => 5281,  // برجور تنفيس ايطالي (4.25) -> برجور تنفيس قنينة ايطالي
        87 => 473,   // طابة سنريبول عادي (0.17) -> طابة فواشة ستربول
        103 => 136,  // تفلون سايروس (0.26) -> تيفلون سايروس 30 م
        104 => 133,  // تفلون صغير (0.09) -> تيفلون صغير
        130 => 115,  // طقم حمام كروم سايروس (16.0) -> اكسسوار حمام سايروس خفيف
        131 => 114,  // طقم حمام كروم سايروس (22.0) -> اكسسوار حمام سايروس ثقيل
        132 => 187,  // FBZ11OC (22.0) -> sr fbz110c
        133 => 177,  // FSZ300B (30.0) -> sr fsz300b
        134 => 166,  // FSTZ400B (21.0) -> sr fstz400b
        135 => 112,  // FBZ100BL (26.0) -> sr fbz100bl
        136 => 2341, // FKZ200B (23.0) -> خلاط مجلى شك اسود سايروس 200 B
        149 => 405,  // مالج سيراميك يد خشب (1.5) -> مالج سيراميك يد خشب
        150 => 647,  // مالج سيراميك يد فيبر (1.2) -> مالج سيراميك
        153 => 2276, // متر ازرق ثقيل 5 متر (0.623) -> متر ازرق 5 متر
        154 => 2277, // متر ازرق ثقيل 5.5 متر (0.733) -> متر ازرق 5.5 متر
        155 => 2278, // متر ازرق ثقيل 7.5 متر (1.0) -> متر ازرق 7.5 متر
        163 => 400,  // وصلة كهربا عادي (1.32) -> وصلة كهربا ثقيلة
        164 => 401,  // وصلة كهربا USB (3.08) -> وصلة كهربا c
    ];

    public function handle(InventoryService $inventory): int
    {
        $filePath = base_path($this->option('file'));
        if (! is_file($filePath)) {
            $this->error("الملف غير موجود: {$filePath}");
            return self::FAILURE;
        }

        $warehouseId = (int) $this->option('warehouse');
        $warehouse = Warehouse::find($warehouseId);
        if (! $warehouse) {
            $this->error("المستودع رقم {$warehouseId} غير موجود.");
            return self::FAILURE;
        }

        $this->info("=== استيراد بيانات مخزون أيوب من ملف: {$this->option('file')} ===");
        $this->info("المستودع: {$warehouse->name} (كود: {$warehouse->code}, المعرف: #{$warehouse->id})");

        // Parse items using python openpyxl
        $items = $this->extractItemsFromExcel($filePath);
        if (empty($items)) {
            $this->error('لم يتم العثور على أي صفوف صالحة في الملف.');
            return self::FAILURE;
        }

        $totalRows = count($items);
        $totalQty = array_sum(array_column($items, 'qty'));
        $totalCost = array_sum(array_column($items, 'total'));

        $this->line("إجمالي البنود المقروءة: {$totalRows}");
        $this->line("إجمالي عدد القطع: " . number_format($totalQty));
        $this->line("إجمالي التكلفة الإجمالية: " . number_format($totalCost, 2) . " $");

        // Load all catalog products for matching and pre-process tokens
        $allProducts = Product::all();
        $processedProducts = $allProducts->map(function ($p) {
            $cleanName = $this->cleanText($p->name_ar);
            $cleanSku = $this->cleanText($p->sku);
            $words = array_filter(explode(' ', $cleanName), fn ($w) => mb_strlen($w) > 1);

            return [
                'model' => $p,
                'id' => $p->id,
                'clean_name' => $cleanName,
                'clean_sku' => $cleanSku,
                'cost_price' => $p->cost_price !== null ? (float) $p->cost_price : null,
                'price' => $p->price !== null ? (float) $p->price : null,
                'words' => $words,
            ];
        });

        $plan = [];
        $matchedCount = 0;
        $createdCount = 0;

        foreach ($items as $item) {
            $product = $this->resolveProduct($item, $processedProducts);
            $action = $product ? 'match' : 'create';

            if ($product) {
                $matchedCount++;
            } else {
                $createdCount++;
            }

            $plan[] = [
                'item' => $item,
                'product' => $product,
                'action' => $action,
            ];
        }

        $this->newLine();
        $this->table(
            ['إجراء', 'عدد الأصناف'],
            [
                ['مطابقة مع منتجات موجودة بالكتالوج', $matchedCount],
                ['إنشاء منتجات جديدة في الكتالوج', $createdCount],
                ['الإجمالي', $totalRows],
            ]
        );

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->newLine();
            $this->warn('تم تشغيل الأمر بوضع المعاينة (--dry-run). لم يتم إجراء أي تعديل على قاعدة البيانات.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('هل تريد تطبيق إدخال المخزون وتحديث الكتالوج الآن؟', true)) {
            $this->line('تم إلغاء العملية.');
            return self::SUCCESS;
        }

        $this->info('جاري إدخال المخزون وتحديث المنتجات...');
        $bar = $this->output->createProgressBar(count($plan));
        $bar->start();

        $stockedCount = 0;
        $unitsStocked = 0;

        DB::transaction(function () use ($plan, $warehouse, $inventory, $bar, &$stockedCount, &$unitsStocked) {
            foreach ($plan as $entry) {
                $item = $entry['item'];
                $product = $entry['product'];

                if (! $product) {
                    $product = $this->createNewProduct($item);
                } else {
                    // Update cost_price if currently missing or zero
                    if ((float) $product->cost_price <= 0 && $item['price'] > 0) {
                        $product->cost_price = $item['price'];
                        $product->save();
                    }
                }

                $qty = (int) $item['qty'];
                $unitCost = (float) $item['price'];

                if ($qty > 0) {
                    $inventory->receive(
                        $product->id,
                        $qty,
                        $warehouse->id,
                        [
                            'key' => 'ayoub-inventory:row:' . $item['row'] . ':product:' . $product->id,
                            'source' => 'opening_stock',
                            'reference' => 'ayoub.xlsx',
                            'reason' => 'رصيد مخزون مستودع أيوب من ملف ayoub.xlsx',
                            'unit_cost' => $unitCost,
                            'created_by' => $warehouse->manager_id ?: 5,
                        ]
                    );

                    $stockedCount++;
                    $unitsStocked += $qty;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(" اكتمل بنجاح إدخال مخزون مستودع {$warehouse->name}!");
        $this->line(" عدد المنتجات التي تم إدخال مخزونها: {$stockedCount}");
        $this->line(" إجمالي الوحدات المستلمة: " . number_format($unitsStocked));
        $this->line(" إجمالي القيمة بالتكلفة: " . number_format($totalCost, 2) . " $");

        return self::SUCCESS;
    }

    /**
     * Extract rows from ayoub.xlsx using python openpyxl.
     */
    private function extractItemsFromExcel(string $filePath): array
    {
        $pythonScript = <<<'PY'
import openpyxl, json, sys

wb = openpyxl.load_workbook(sys.argv[1], data_only=True)
ws = wb.active

items = []
for r in range(13, ws.max_row + 1):
    desc = ws.cell(r, 3).value
    qty = ws.cell(r, 6).value
    price = ws.cell(r, 7).value
    if not desc and not qty: continue
    if desc and 'المجموع' in str(desc): continue
    
    items.append({
        'row': r,
        'seq': ws.cell(r, 1).value,
        'size': str(ws.cell(r, 2).value).strip() if ws.cell(r, 2).value is not None else None,
        'desc': str(desc).strip(),
        'qty': float(qty or 0),
        'price': float(price or 0),
        'unit': str(ws.cell(r, 8).value).strip() if ws.cell(r, 8).value is not None else 'قطعة',
        'total': float(ws.cell(r, 9).value or 0)
    })

print(json.dumps(items, ensure_ascii=False))
PY;

        $process = new Process(['python3', '-c', $pythonScript, $filePath]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('فشل قراءة ملف الإكسل: ' . $process->getErrorOutput());
            return [];
        }

        return json_decode($process->getOutput(), true) ?: [];
    }

    /**
     * Resolve existing product by curated mapping, exact SKU/Name, or token similarity.
     */
    private function resolveProduct(array $item, $processedProducts): ?Product
    {
        $row = (int) $item['row'];

        // 1. Curated map
        if (isset(self::CURATED_MAPPINGS[$row])) {
            $targetId = self::CURATED_MAPPINGS[$row];
            $found = $processedProducts->firstWhere('id', $targetId);
            if ($found) {
                return $found['model'];
            }
        }

        $desc = $this->cleanText($item['desc']);
        $size = $this->cleanText($item['size'] ?? '');
        $price = (float) $item['price'];
        $fullDesc = $size ? "{$desc} {$size}" : $desc;

        // 2. Exact clean SKU or Name
        foreach ($processedProducts as $p) {
            $cleanSku = $p['clean_sku'];
            if ($cleanSku && ($cleanSku === $desc || $cleanSku === str_replace('o', '0', $desc))) {
                return $p['model'];
            }

            $cleanName = $p['clean_name'];
            if ($cleanName === $desc || ($size && $cleanName === $fullDesc)) {
                return $p['model'];
            }
        }

        // 3. High confidence token overlap + cost price agreement
        $descCode = str_replace('o', '0', $desc);
        if (strlen($descCode) >= 5 && preg_match('/[0-9]/', $descCode)) {
            foreach ($processedProducts as $p) {
                if (str_contains($p['clean_name'], $descCode) || str_contains($p['clean_name'], $desc)) {
                    return $p['model'];
                }
            }
        }

        $itemWords = array_filter(
            explode(' ', $fullDesc),
            fn ($w) => mb_strlen($w) > 1 && ! in_array($w, ['م', 'لون', 'يد', 'مع', 'بدون', 'نوع', 'انش', 'سم', 'cm', 'mm'])
        );

        if (empty($itemWords)) {
            return null;
        }

        $candidates = [];
        foreach ($processedProducts as $p) {
            $pWords = $p['words'];
            $common = array_intersect($itemWords, $pWords);
            if (empty($common)) {
                continue;
            }

            $overlap = count($common) / count($itemWords);
            if ($overlap < 0.45) {
                continue;
            }

            $score = $overlap * 50;
            $pCost = $p['cost_price'];
            $pPrice = $p['price'];

            if ($pCost !== null && abs($pCost - $price) < 0.01) {
                $score += 40;
            } elseif ($pCost !== null && abs($pCost - $price) < 0.1) {
                $score += 20;
            } elseif ($pPrice !== null && abs($pPrice - $price) < 0.01) {
                $score += 20;
            }

            if ($size && str_contains($p['clean_name'], $size)) {
                $score += 15;
            }

            $candidates[] = ['model' => $p['model'], 'score' => $score];
        }

        if (! empty($candidates)) {
            usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
            if ($candidates[0]['score'] >= 60) {
                return $candidates[0]['model'];
            }
        }

        return null;
    }

    /**
     * Create a new product for items not yet in the catalogue.
     */
    private function createNewProduct(array $item): Product
    {
        $desc = trim($item['desc']);
        $size = ! empty($item['size']) ? trim($item['size']) : null;

        // Fix obvious spelling in raw name
        $nameAr = $desc;
        $nameAr = str_replace('لقمات بواي', 'لقمات بواري', $nameAr);
        $nameAr = str_replace('لزبق ورق', 'لزيق ورق', $nameAr);
        $nameAr = str_replace('قلب خلا نحاس', 'قلب خلاط نحاس', $nameAr);
        $nameAr = str_replace('نبريش زراعي', 'بربيش زراعي', $nameAr);

        if ($size && ! str_contains($nameAr, $size)) {
            $nameAr .= " {$size}";
        }

        $cost = (float) $item['price'];
        $categoryId = $this->inferCategoryId($nameAr);

        return Product::create([
            'name_ar' => $nameAr,
            'name_en' => null,
            'slug' => ProductIdentifiers::uniqueSlug($nameAr),
            'sku' => ProductIdentifiers::nextSku(),
            'category_id' => $categoryId,
            'size' => $size,
            'unit' => $item['unit'] ?: 'قطعة',
            'cost_price' => $cost > 0 ? $cost : null,
            'price' => null,
            'show_price' => false,
            'stock_quantity' => 0,
            'in_stock' => (int) $item['qty'] > 0,
            'is_active' => true,
        ]);
    }

    /**
     * Infer appropriate category based on keywords.
     */
    private function inferCategoryId(string $name): ?int
    {
        $map = [
            13 => ['خلاط', 'سحابة دوش', 'عامود دوش'],
            14 => ['حنفية', 'سكر', 'محبس', 'رداد', 'شراقة', 'برجور', 'فواشة'],
            15 => ['شطاف', 'دوش', 'مصفاية', 'صباب', 'هراب', 'ريكار', 'مجلى', 'مغسلة', 'كرسي', 'طقم حمام'],
            16 => ['وصلة', 'بربيش', 'خرطوم', 'بوري', 'بواري', 'كوع', 'تي', 'نقاصة', 'راكور', 'عزقة'],
            17 => ['متر', 'مالج', 'مطرقة', 'كاوي', 'قفل', 'ديسك', 'رنش', 'فتاحة', 'مفك', 'زرادية', 'بانسة'],
            18 => ['لزيق', 'لاصق', 'معجونة', 'تيب', 'سيليكون', 'بلومات', 'ايبوكسي'],
            20 => ['كهربا', 'USB'],
        ];

        foreach ($map as $catId => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_stripos($name, $kw) !== false) {
                    return $catId;
                }
            }
        }

        return 15; // Default to Sanitary Ware & Bath
    }

    private function cleanText(?string $s): string
    {
        if (! $s) {
            return '';
        }

        $s = trim($s);
        $s = preg_replace('/[أإآ]/u', 'ا', $s);
        $s = preg_replace('/[ة]/u', 'ه', $s);
        $s = preg_replace('/[ى]/u', 'ي', $s);
        $s = preg_replace('/[\"\'\`\’\‘]/u', '', $s);
        $s = preg_replace('/[\/\-\_\(\)\*]/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return mb_strtolower($s, 'UTF-8');
    }
}
