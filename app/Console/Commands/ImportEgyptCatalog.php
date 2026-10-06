<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Support\ProductIdentifiers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportEgyptCatalog extends Command
{
    protected $signature = 'egypt:import-catalog
                            {--file=scripts/egypt_catalog_full.json : Path to the catalog JSON}
                            {--fresh : Remove previously imported Egyptian products before re-importing}';

    protected $description = 'Import Egyptian products from egypt.pdf catalog JSON into new Egypt categories with images, specs and variants';

    /**
     * Category definitions: parent and 14 subcategories.
     */
    private const CATEGORIES = [
        'parent' => [
            'name_ar' => 'المنتجات المصرية',
            'name_en' => 'Egyptian Products',
            'slug' => 'egyptian-products',
            'description_ar' => 'تشكيلة المنتجات والأدوات الصحية والسباكة والخلاطات المصرية الأصلية عالية الجودة',
            'description_en' => 'Premium Egyptian sanitary products, plumbing fixtures, valves and mixers',
            'icon' => 'fa-faucet',
            'sort_order' => 145,
        ],
        'subcategories' => [
            'egyptian-angle-valves' => [
                'name_ar' => 'سكورة زاوية مصرية',
                'name_en' => 'Egyptian Angle Valves',
                'description_ar' => 'سكورة ومحابس زاوية مصرية: ماسة، مربع، مودرن، نحاس، هوايي، مخرجين',
                'description_en' => 'Egyptian angle valves in diamond, square, modern, brass and dual-outlet styles',
                'icon' => 'fa-faucet',
                'sort_order' => 1,
            ],
            'egyptian-gate-valves' => [
                'name_ar' => 'سكورة دحلة وبروجور تنفيس مصري',
                'name_en' => 'Egyptian Gate Valves & Air Vents',
                'description_ar' => 'سكورة دحلة نحاسية خفيفة وتقيلة وبروجورات تنفيس هواء مصرية بمقاسات 1/2 حتى 2 انش',
                'description_en' => 'Egyptian brass gate valves and air relief vents from 1/2" up to 2"',
                'icon' => 'fa-valve',
                'sort_order' => 2,
            ],
            'egyptian-taps' => [
                'name_ar' => 'حنفيات مصرية',
                'name_en' => 'Egyptian Taps & Bibcocks',
                'description_ar' => 'حنفيات دحلة وموديل بوغاتي وفتيس وكروم ونحاس برم راكور وكلاسيك هاواي',
                'description_en' => 'Egyptian bibcock taps, Bugatti style, long bibcocks and quick-turn brass faucets',
                'icon' => 'fa-faucet',
                'sort_order' => 3,
            ],
            'egyptian-ppr-fittings' => [
                'name_ar' => 'قطع وسكورة PPR مصرية',
                'name_en' => 'Egyptian PPR Valves & Fittings',
                'description_ar' => 'سكورة لحام وتيهات وتيهات بسن وشد وصل PPR هاواي بلس',
                'description_en' => 'Hawaii Plus PPR welding stop valves, tees and core unions',
                'icon' => 'fa-pipe',
                'sort_order' => 4,
            ],
            'egyptian-brass-fittings' => [
                'name_ar' => 'أكر بسنة ونحاسيات وردادات مصرية',
                'name_en' => 'Egyptian Brass Fittings & Check Valves',
                'description_ar' => 'أكر بسنة كروم ونحاس، صبابات عدم رجوع، تيه رداد، وشد وصل نحاس مصري',
                'description_en' => 'Egyptian brass threaded adaptors, non-return check valves, tee checks and brass unions',
                'icon' => 'fa-wrench',
                'sort_order' => 5,
            ],
            'egyptian-drains' => [
                'name_ar' => 'هرابات وسيفونات مصرية',
                'name_en' => 'Egyptian Waste Drains & Traps',
                'description_ar' => 'هرابات وسيفونات مجلى ومغسلة تقيل وخفيف ابيض ورمادي مع صباب وبدون صباب',
                'description_en' => 'Egyptian sink and basin waste drains and strainers in heavy and regular duty',
                'icon' => 'fa-sink',
                'sort_order' => 6,
            ],
            'egyptian-float-valves' => [
                'name_ar' => 'فواشات وعدة صندوق وشراقات مصرية',
                'name_en' => 'Egyptian Float Valves & Cistern Kits',
                'description_ar' => 'فواشات خزان مع طابة، عدة صندوق تواليت سفلية وجانبية، شراقات نحاس، وسماعات دوش',
                'description_en' => 'Egyptian tank float valves, cistern flush mechanisms, brass foot valves and shower heads',
                'icon' => 'fa-toilet',
                'sort_order' => 7,
            ],
            'egyptian-standard-mixers' => [
                'name_ar' => 'خلاطات مصرية - أطقم وموديلات كلاسيك ومودرن',
                'name_en' => 'Egyptian Standard Mixers',
                'description_ar' => 'خلاطات مغسلة ومطبخ ودوش وشك عالي: موديل 379 معكوف كروم وذهبي وروز غولد، 444، 545، 916، 918 تركي، 009، 003، 001، شبح 131',
                'description_en' => 'Egyptian mixer collections including models 379 curved, 444, 545, 916, 918, 009, 003, 001 and 131',
                'icon' => 'fa-shower',
                'sort_order' => 8,
            ],
            'egyptian-hawaii-mixers' => [
                'name_ar' => 'خلاطات هاوايي مصرية',
                'name_en' => 'Egyptian Hawaii Mixers',
                'description_ar' => 'خلاطات هاوايي الفاخرة موديلات 601 كروم، 394 ذهبي، 571 ذهبي واسود، 572 ذهبي وملكي كروم، 003 ذهبي',
                'description_en' => 'Luxury Hawaii mixer sets in Chrome, Gold and Black (models 601, 394, 571, 572, 003)',
                'icon' => 'fa-faucet',
                'sort_order' => 9,
            ],
            'egyptian-chef-mixers' => [
                'name_ar' => 'خلاطات شيف وسيليكون مصرية',
                'name_en' => 'Egyptian Chef & Flexible Kitchen Mixers',
                'description_ar' => 'خلاطات شيف مطبخ راصور وسيليكون 2*1 و 1*1 وفلتر ومع كباسة وتواليت',
                'description_en' => 'Egyptian commercial style chef spring mixers, silicone hoses and 2-in-1 kitchen mixers',
                'icon' => 'fa-faucet',
                'sort_order' => 10,
            ],
            'egyptian-concealed-mixers' => [
                'name_ar' => 'خلاطات دفن وبيانو وديكور مصرية',
                'name_en' => 'Egyptian Concealed, Piano & Decor Mixers',
                'description_ar' => 'خلاطات دفن راوند واوبلنج واسكوير واكليك واسكرين تربل، خلاطات دش بيانو ديجيتال، وخلاطات شجرة ديكور',
                'description_en' => 'Concealed wall mixers, triple concealed sets, digital piano shower mixers and decorative faucets',
                'icon' => 'fa-faucet',
                'sort_order' => 11,
            ],
            'egyptian-water-heaters' => [
                'name_ar' => 'سخانات وبلرات مصرية',
                'name_en' => 'Egyptian Water Heaters',
                'description_ar' => 'سخانات وبلرات مياه كهربائية مصرية سعة 50 لتر و 80 لتر',
                'description_en' => 'Egyptian domestic electric water heaters (50L & 80L)',
                'icon' => 'fa-fire-burner',
                'sort_order' => 12,
            ],
            'egyptian-flexible-hoses' => [
                'name_ar' => 'وصلات مرنة ستانلس وبولي مصرية (تو ام)',
                'name_en' => 'Egyptian Flexible Hoses - 2M',
                'description_ar' => 'وصلات مرنة 1/2*1/2 و 1/2*3/8 ووصلات شجرة ستانلس وبولي ماركة تو ام بمقاسات 40 حتى 100 سم',
                'description_en' => '2M Egyptian flexible stainless steel and reinforced poly connection hoses from 40cm to 100cm',
                'icon' => 'fa-link',
                'sort_order' => 13,
            ],
            'egyptian-shower-hoses' => [
                'name_ar' => 'خراطيم وبرابيج دوش وغسالة وتواليت مصرية',
                'name_en' => 'Egyptian Hoses',
                'description_ar' => 'برابيج دوش وتواليت 3 طبقات PVC ابيض واسود ورمادي، وبرابيج غسالة ماركة تو ام',
                'description_en' => '2M 3-layer PVC shower, bidet and washing machine supply hoses',
                'icon' => 'fa-shower',
                'sort_order' => 14,
            ],
        ],
    ];

    public function handle(): int
    {
        $file = base_path($this->option('file'));

        if (! is_file($file)) {
            $this->error("ملف البيانات غير موجود: {$file}");
            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($file), true);

        if (! is_array($data) || empty($data)) {
            $this->error('ملف البيانات فارغ أو غير صالح.');
            return self::FAILURE;
        }

        $this->info('— بدء تجهيز الفئات المصرية...');
        $categories = $this->ensureCategories();
        $this->info("تم تجهيز الفئة الرئيسية و " . count($categories) . " فئات فرعية بنجاح.");

        if ($this->option('fresh')) {
            $this->warn('جاري حذف المنتجات المصرية السابقة (--fresh)...');
            $catIds = array_values($categories);
            $parent = Category::where('slug', self::CATEGORIES['parent']['slug'])->first();
            if ($parent) {
                $catIds[] = $parent->id;
            }
            Product::whereIn('category_id', $catIds)->delete();
            $this->info('تم الحذف.');
        }

        $warehouse = Warehouse::where('code', 'WH-001')->first() ?? Warehouse::first();

        $this->info("— استيراد " . count($data) . " منتج...");
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        $createdProducts = 0;
        $createdVariants = 0;

        DB::beginTransaction();
        try {
            foreach ($data as $item) {
                $catSlug = $item['category_slug'] ?? 'egyptian-products';
                $catId = $categories[$catSlug] ?? null;

                $nameAr = trim($item['name_ar']);
                $nameEn = trim($item['name_en'] ?? '') ?: null;
                $price = (float) ($item['price'] ?? 0);
                $costPrice = round($price * 0.85, 2);

                $sku = $this->uniqueProductSku($item['sku'] ?? null);
                $slug = ProductIdentifiers::uniqueSlug($nameAr, $nameEn);

                $product = Product::create([
                    'category_id' => $catId,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'slug' => $slug,
                    'description_ar' => $item['description_ar'] ?? null,
                    'description_en' => $nameEn,
                    'price' => $price,
                    'cost_price' => $costPrice,
                    'unit' => $item['unit'] ?? 'قطعة',
                    'stock_quantity' => (int) ($item['packaging'] ?? 50) ?: 50,
                    'brand' => $item['brand'] ?? 'مصري',
                    'model' => $item['model'] ?? null,
                    'sku' => $sku,
                    'image_main' => $item['image_main'] ?? null,
                    'show_price' => true,
                    'in_stock' => true,
                    'is_active' => true,
                    'sort_order' => $createdProducts + 1,
                ]);

                $createdProducts++;

                // Record stock in warehouse_inventory
                if ($warehouse) {
                    WarehouseInventory::updateOrCreate(
                        ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'product_variant_id' => null],
                        [
                            'quantity' => $product->stock_quantity,
                            'available_quantity' => $product->stock_quantity,
                            'cost_basis' => 'FIFO',
                        ]
                    );
                }

                // Create ProductVariants
                $variantsData = $item['variants'] ?? [];
                foreach ($variantsData as $v) {
                    $vSku = $this->uniqueVariantSku($v['sku'] ?? $sku);
                    $vPrice = (float) ($v['price'] ?? $price);
                    $vCost = round($vPrice * 0.85, 2);

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $vSku,
                        'price' => $vPrice,
                        'cost_price' => $vCost,
                        'stock_quantity' => (int) ($v['stock_quantity'] ?? 50),
                        'size' => $v['size'] ?? null,
                        'color' => $v['color'] ?? null,
                        'material' => $v['material'] ?? null,
                        'specs' => ProductVariant::normaliseSpecs($v['specs'] ?? null),
                    ]);

                    $createdVariants++;

                    if ($warehouse) {
                        WarehouseInventory::updateOrCreate(
                            ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id],
                            [
                                'quantity' => $variant->stock_quantity,
                                'available_quantity' => $variant->stock_quantity,
                                'cost_basis' => 'FIFO',
                            ]
                        );
                    }
                }

                $bar->advance();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("\nحدث خطأ أثناء الاستيراد: " . $e->getMessage());
            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ اكتمل الاستيراد بنجاح!");
        $this->info("  - منتجات جديدة: {$createdProducts}");
        $this->info("  - خيارات ومتغيرات (Variants): {$createdVariants}");
        $this->info("  - فئات المنتجات المصرية: " . count($categories));

        return self::SUCCESS;
    }

    /**
     * Create or retrieve parent category and 14 subcategories.
     *
     * @return array<string, int> slug => category_id
     */
    private function ensureCategories(): array
    {
        $parentDef = self::CATEGORIES['parent'];
        $parent = Category::firstOrCreate(
            ['slug' => $parentDef['slug']],
            [
                'name_ar' => $parentDef['name_ar'],
                'name_en' => $parentDef['name_en'],
                'description' => $parentDef['description_ar'],
                'description_ar' => $parentDef['description_ar'],
                'description_en' => $parentDef['description_en'],
                'icon' => $parentDef['icon'],
                'sort_order' => $parentDef['sort_order'],
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        $result = [];

        foreach (self::CATEGORIES['subcategories'] as $slug => $def) {
            $cat = Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'name_ar' => $def['name_ar'],
                    'name_en' => $def['name_en'],
                    'description' => $def['description_ar'],
                    'description_ar' => $def['description_ar'],
                    'description_en' => $def['description_en'],
                    'icon' => $def['icon'],
                    'sort_order' => $def['sort_order'],
                    'is_active' => true,
                    'parent_id' => $parent->id,
                ]
            );

            // In case category already existed without parent_id
            if ($cat->parent_id !== $parent->id) {
                $cat->parent_id = $parent->id;
                $cat->save();
            }

            $result[$slug] = $cat->id;
        }

        return $result;
    }

    private function uniqueProductSku(?string $base): string
    {
        $base = trim((string) $base) ?: 'EGY-' . strtoupper(Str::random(6));
        $candidate = $base;
        $i = 2;

        while (Product::where('sku', $candidate)->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        return $candidate;
    }

    private function uniqueVariantSku(?string $base): string
    {
        $base = trim((string) $base) ?: 'EGY-VAR-' . strtoupper(Str::random(6));
        $candidate = $base;
        $i = 2;

        while (ProductVariant::where('sku', $candidate)->exists()) {
            $candidate = $base . '-V' . $i;
            $i++;
        }

        return $candidate;
    }
}
