<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\ProductIdentifiers;
use Illuminate\Console\Command;

/**
 * One-time backfill that re-derives every product's slug from its name.
 *
 * The slug was once generated three different ways — the seeded catalogue
 * held Arabic slugs, the Excel import transliterated through Str::slug(), and
 * the admin form demanded the field by hand — so rows drifted into whatever a
 * given import happened to produce. This makes the whole table agree with the
 * single function the code now uses everywhere.
 *
 * A product's slug is its public URL, so this is explicit surgery rather than
 * an automatic side effect. `--dry-run` reports what would change without
 * writing anything.
 */
class ProductsBackfillSlugs extends Command
{
    protected $signature = 'products:backfill-slugs {--dry-run : Report what would change without saving}';

    protected $description = 'Regenerate every product slug from its name, the same way the catalogue generates it today';

    public function handle(): int
    {
        $products = Product::orderBy('id')->get();

        if ($products->isEmpty()) {
            $this->info('لا يوجد منتجات.');
            return self::SUCCESS;
        }

        $changed = 0;
        $this->newLine();
        $this->line('— إعادة توليد slugs من الأسماء');
        $this->newLine();

        $rows = [];

        foreach ($products as $product) {
            // Unique within the batch, ignoring this product's own id so a
            // slug already holding the name does not suffix itself.
            $slug = ProductIdentifiers::uniqueSlug(
                $product->name_ar,
                $product->name_en,
                $product->id
            );

            if ($slug === $product->slug) {
                continue;
            }

            $rows[] = [
                $product->id,
                mb_substr((string) $product->name_ar, 0, 30),
                $product->slug,
                $slug,
            ];

            if (! $this->option('dry-run')) {
                $product->slug = $slug;
                $product->saveQuietly();
                $changed++;
            }
        }

        if (empty($rows)) {
            $this->info('  جميع slugs متطابقة بالفعل.');
            return self::SUCCESS;
        }

        $this->table(
            ['المنتج', 'الاسم', 'القديم', 'الجديد'],
            $rows
        );

        $this->newLine();
        $verb = $this->option('dry-run') ? 'سيتغير' : 'تم تحديث';
        $this->info("  {$verb} slugs عدد: ".count($rows));

        return self::SUCCESS;
    }
}