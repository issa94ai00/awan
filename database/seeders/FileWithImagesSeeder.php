<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the catalogue exported from public/file_with_images.html.
 *
 * Each item in the sheet becomes one Product (name_ar + product image) and
 * every size row becomes one ProductVariant carrying that size's own price and
 * stock count. Append-only: existing products are left untouched.
 */
class FileWithImagesSeeder extends Seeder
{
    private const DATA_FILE = 'data/file_with_images_products.json';

    public function run(): void
    {
        $file = database_path('seeders/' . self::DATA_FILE);

        if (!is_file($file)) {
            $this->command?->error("ملف البيانات غير موجود: {$file}");
            return;
        }

        $items = json_decode(file_get_contents($file), true);

        if (!is_array($items) || !$items) {
            $this->command?->error('لم يُقرأ أي صنف من الملف.');
            return;
        }

        $count = 0;
        $variantCount = 0;

        DB::transaction(function () use ($items, &$count, &$variantCount) {
            foreach ($items as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $image = $this->cleanPath($item['image'] ?? null);

                $product = Product::create([
                    'name_ar' => $name,
                    'slug' => $this->uniqueSlug($name),
                    'price' => $this->firstPrice($item['rows'] ?? []),
                    'image_main' => $image,
                    'image_gallery' => $image ? json_encode([$image]) : null,
                    'stock_quantity' => $this->totalStock($item['rows'] ?? []),
                    'in_stock' => $this->totalStock($item['rows'] ?? []) > 0,
                    'show_price' => $this->firstPrice($item['rows'] ?? []) !== null,
                    'is_active' => true,
                    'sort_order' => (int) ($item['id'] ?? 0),
                ]);

                foreach (($item['rows'] ?? []) as $index => $row) {
                    $price = $this->toFloat($row['price'] ?? null) ?? 0;

                    ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => 'FW-' . $product->id . '-' . ($index + 1),
                        'size' => trim((string) ($row['size'] ?? '')) ?: null,
                        'price' => $price,
                        'stock_quantity' => $this->toInt($row['count'] ?? null),
                    ]);

                    $variantCount++;
                }

                $count++;
            }
        });

        $this->command?->info("تم إنشاء {$count} منتجاً و {$variantCount} مقاساً (فاريانت).");
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '-', null) ?: 'item';
        $slug = $base;
        $n = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    private function cleanPath(?string $path): ?string
    {
        $path = trim((string) $path);
        return $path === '' ? null : ltrim($path, '/');
    }

    /** @param array<int,array{size:?string,price:?string,count:?string}> $rows */
    private function firstPrice(array $rows): ?float
    {
        foreach ($rows as $row) {
            $price = $this->toFloat($row['price'] ?? null);
            if ($price !== null) {
                return $price;
            }
        }

        return null;
    }

    /** @param array<int,array{size:?string,price:?string,count:?string}> $rows */
    private function totalStock(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += $this->toInt($row['count'] ?? null);
        }

        return $sum;
    }

    private function toFloat(mixed $value): ?float
    {
        $value = $this->normalizeDigits($value);
        if ($value === '' || $value === '—') {
            return null;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', $value);
        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        $n = (float) $normalized;
        return is_finite($n) ? round($n, 2) : null;
    }

    private function toInt(mixed $value): int
    {
        $value = $this->normalizeDigits($value);
        if ($value === '' || $value === '—') {
            return 0;
        }

        return max(0, (int) preg_replace('/[^0-9]/', '', (string) $value));
    }

    /** Converts Arabic-Indic digits to ASCII so prices/counts parse reliably. */
    private function normalizeDigits(mixed $value): string
    {
        $value = trim((string) $value);
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.',
        ]);
    }
}
