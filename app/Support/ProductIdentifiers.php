<?php

namespace App\Support;

use App\Models\Product;

/**
 * Slugs and SKUs, generated the same way wherever a product is created.
 *
 * The admin form demanded both by hand, the Excel import derived a slug with
 * Str::slug(), and the seeded catalogue holds Arabic slugs — three answers to
 * one question. This is the single answer.
 */
class ProductIdentifiers
{
    /**
     * A URL-safe slug that keeps the script it was written in.
     *
     * Str::slug() transliterates: "كباسة شطاف" becomes "kbas-shtaf". That is
     * not what this catalogue holds — 1,360 of its products carry an Arabic
     * slug — and changing a live product's slug changes its public URL. So
     * the letters are kept and only the separators are normalised, which
     * leaves both conventions already in the table intact.
     */
    public static function slugify(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        // Arabic tatweel and the bidi/zero-width marks a copy-paste drags in
        // are invisible in the field but would survive into the URL.
        $value = preg_replace('/[\x{0640}\x{200B}-\x{200F}\x{202A}-\x{202E}]/u', '', $value);

        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($value, 'UTF-8'));

        return trim((string) $value, '-');
    }

    /**
     * A slug no other product is using. Falls back through the names, then to
     * a generated one, so this never returns an empty string.
     */
    public static function uniqueSlug(?string $base, ?string $fallback = null, ?int $ignoreId = null): string
    {
        $slug = static::slugify($base) ?: static::slugify($fallback);

        if ($slug === '') {
            $slug = 'product-'.substr(md5(($base ?? '').uniqid('', true)), 0, 10);
        }

        $candidate = $slug;
        $counter = 2;

        while (Product::where('slug', $candidate)->where('id', '!=', $ignoreId ?? 0)->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * The next code in the "SKU-00001" series.
     *
     * Deliberately its own series rather than an extension of the legacy
     * "L182" codes: those were assigned by hand and guessing their next value
     * would hand out a code someone is still holding on paper.
     */
    public static function nextSku(): string
    {
        $highest = Product::where('sku', 'LIKE', 'SKU-%')
            ->selectRaw("MAX(CAST(SUBSTRING(sku, 5) AS UNSIGNED)) AS n")
            ->value('n');

        $next = ((int) $highest) + 1;

        do {
            $candidate = 'SKU-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (Product::where('sku', $candidate)->exists());

        return $candidate;
    }
}
