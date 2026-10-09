<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductIdentifiers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportIngcoCatalog extends Command
{
    protected $signature = 'ingco:import-catalog
                            {--file=scripts/ingco_catalog.json : JSON written by scripts/extract_ingco_xlsx.py}';

    protected $description = 'Import the Ingco price list into the Ingco category (re-running updates products by their Ingco code)';

    private const CATEGORY = [
        'slug' => 'ingco',
        'name_ar' => 'إنكو',
        'name_en' => 'INGCO',
        'description_ar' => 'نشرة أسعار عدد وأدوات إنكو: العدد الكهربائية والبطارية والعدد اليدوية وملحقاتها',
        'description_en' => 'INGCO power, cordless and hand tools with their accessories',
        'icon' => 'fa-toolbox',
    ];

    public function handle(): int
    {
        $file = base_path($this->option('file'));

        if (! is_file($file)) {
            $this->error("ملف البيانات غير موجود: {$file}");
            return self::FAILURE;
        }

        $items = json_decode(file_get_contents($file), true);

        if (! is_array($items) || empty($items)) {
            $this->error('ملف البيانات فارغ أو غير صالح.');
            return self::FAILURE;
        }

        $category = $this->ensureCategory();
        $created = 0;
        $updated = 0;

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        DB::transaction(function () use ($items, $category, $bar, &$created, &$updated) {
            foreach ($items as $item) {
                $code = trim($item['code']);
                $product = Product::where('sku', $code)->first();

                if ($product && $product->category_id !== $category->id) {
                    // Same code already used by a product outside Ingco — leave it alone.
                    $this->newLine();
                    $this->warn("تخطي {$code}: الرمز مستخدم لمنتج آخر (#{$product->id}).");
                    $bar->advance();
                    continue;
                }

                $attributes = [
                    'category_id' => $category->id,
                    'name_ar' => $item['name_ar'],
                    'description_ar' => $item['description_ar'],
                    'price' => $item['price'],
                    'cost_price' => $item['price_usd'],
                    'pack_quantity' => $item['pack_quantity'],
                    'brand' => 'INGCO',
                    'model' => $code,
                    'image_main' => $item['image_main'],
                    'image_gallery' => json_encode($item['image_gallery'] ?? []),
                    'sort_order' => $item['row'] - 2,
                ];

                if ($product) {
                    $product->update($attributes);
                    $updated++;
                } else {
                    Product::create($attributes + [
                        'sku' => $code,
                        'slug' => ProductIdentifiers::uniqueSlug($item['name_ar'].' '.$code, $code),
                        'unit' => 'piece',
                        'stock_quantity' => 0,
                        'show_price' => true,
                        'is_active' => true,
                    ]);
                    $created++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('✓ اكتمل استيراد إنكو');
        $this->info("  - منتجات جديدة: {$created}");
        $this->info("  - منتجات محدّثة: {$updated}");

        return self::SUCCESS;
    }

    private function ensureCategory(): Category
    {
        $def = self::CATEGORY;

        return Category::firstOrCreate(
            ['slug' => $def['slug']],
            [
                'name_ar' => $def['name_ar'],
                'name_en' => $def['name_en'],
                'description' => $def['description_ar'],
                'description_ar' => $def['description_ar'],
                'description_en' => $def['description_en'],
                'icon' => $def['icon'],
                'sort_order' => (int) Category::whereNull('parent_id')->max('sort_order') + 1,
                'is_active' => true,
                'parent_id' => null,
            ]
        );
    }
}
