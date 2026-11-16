<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Names the sale lines priced below what the goods cost.
 *
 * Selling below cost is allowed (clearance, a promised price) but should be
 * seen, not slip through. A line is compared per base unit, after its own
 * discount, against the size's cost when it has one and the product's
 * otherwise — the same cost the sale screens pick. Document-level discounts
 * are not spread over the lines.
 *
 * The messages travel back as `warnings` on the save response; the admin API
 * client shows any it receives.
 */
class BelowCostWarnings
{
    /**
     * @param  iterable<object>  $lines  saved lines with product_id, product_variant_id,
     *                                   quantity, unit_price, and optionally
     *                                   unit_multiplier, discount, product_name
     * @return list<string>
     */
    public static function forLines(iterable $lines): array
    {
        $lines = collect($lines)->filter(fn ($line) => $line->product_id);
        if ($lines->isEmpty()) {
            return [];
        }

        $products = Product::whereIn('id', $lines->pluck('product_id')->unique())
            ->get(['id', 'name_ar', 'cost_price'])->keyBy('id');
        $variants = ProductVariant::whereIn('id', $lines->pluck('product_variant_id')->filter()->unique())
            ->get(['id', 'cost_price'])->keyBy('id');

        $warnings = [];

        foreach ($lines as $line) {
            $product = $products[$line->product_id] ?? null;
            $variantCost = (float) ($variants[$line->product_variant_id ?? 0]->cost_price ?? 0);
            $cost = $variantCost > 0 ? $variantCost : (float) ($product->cost_price ?? 0);
            $quantity = (float) $line->quantity;

            if ($cost <= 0 || $quantity <= 0) {
                continue;
            }

            $multiplier = (float) ($line->unit_multiplier ?? 1) ?: 1;
            $net = (float) $line->unit_price - ((float) ($line->discount ?? 0)) / $quantity;
            $perBaseUnit = $net / $multiplier;

            if ($perBaseUnit < $cost - 0.00001) {
                $name = $line->product_name ?? $product?->name_ar ?? ('#'.$line->product_id);
                $warnings[] = sprintf(
                    '«%s»: سعر البيع %s أقل من التكلفة %s للوحدة.',
                    $name,
                    self::money($perBaseUnit),
                    self::money($cost),
                );
            }
        }

        return $warnings;
    }

    private static function money(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ','), '0'), '.');
    }
}
