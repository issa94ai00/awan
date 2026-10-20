<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Gives a product sold by weight its two units: the gram and the kilogram.
 *
 * Stock is counted in whole base units, so a product counted in kilos cannot
 * sell 250 g — a quarter of a kilo has no integer. The gram is therefore the
 * base: one غرام unit at ×1 and one كغ unit of 1000 g at ×1000 the price. A
 * product still counted in kilos is first moved onto grams, which multiplies
 * every count of it by 1000 and divides every per-unit price by 1000. Totals
 * — what stock is worth, what a document charged — come out the same, so the
 * ledger has nothing to correct.
 */
class WeightUnitService
{
    /** What the product's base unit is called once it is counted in grams. */
    public const GRAM_UNIT = 'غرام';

    /**
     * Columns holding a count of the product, and columns holding a price or
     * cost per one of it, wherever the product appears outside a sale line.
     * Sale lines keep their unit and are rewritten separately below.
     *
     * @var array<string, array{0: list<string>, 1: list<string>}>
     */
    private const SCALED = [
        'products' => [['stock_quantity', 'min_stock', 'max_stock'], ['price', 'cost_price']],
        'product_variants' => [['stock_quantity'], ['price', 'cost_price']],
        'warehouse_inventory' => [
            ['quantity', 'available_quantity', 'reserved_quantity', 'damaged_quantity',
                'quarantined_quantity', 'reorder_point', 'safety_stock', 'average_daily_sales'],
            [],
        ],
        'product_warehouse_assignments' => [['min_stock_level', 'max_stock_level', 'safety_stock'], []],
        'inventory_cost_layers' => [['received_quantity', 'remaining_quantity'], ['unit_cost']],
        // quantity_available is generated from these two.
        'product_batches' => [['quantity', 'quantity_reserved'], ['unit_cost']],
        'stock_movements' => [['quantity'], ['unit_cost']],
        'cycle_count_items' => [['expected_quantity', 'counted_quantity', 'variance'], ['unit_cost']],
        'inventory_transfer_items' => [['quantity_requested', 'quantity_shipped', 'quantity_received'], ['unit_cost']],
        'reorder_alerts' => [['current_quantity', 'reorder_point', 'safety_stock', 'suggested_order_quantity'], []],
        'purchase_order_items' => [['quantity', 'received_quantity'], ['unit_price', 'received_unit_cost', 'sale_price']],
        'purchase_receipt_items' => [['quantity'], ['unit_price', 'sale_price']],
        'purchase_return_items' => [['quantity'], ['unit_cost', 'unit_price']],
        'quote_items' => [['quantity'], ['unit_price']],
        'credit_note_items' => [['quantity'], ['unit_price']],
        'cart_items' => [['quantity'], ['price']],
        'order_items' => [['quantity'], ['price', 'price_after_discount']],
        'production_orders' => [['quantity'], []],
    ];

    /** Sale lines: they keep the unit they sold in, so only that unit's size changes. */
    private const SALE_LINES = ['sales_order_items', 'invoice_items'];

    /** The largest price multiplier `product_units.price_multiplier` (8,4) can hold. */
    private const MAX_PRICE_MULTIPLIER = 9999.9999;

    /**
     * Creates — or brings into line — the product's غرام and كغ units.
     *
     * @return array{converted: bool, price_rounded: bool, units: \Illuminate\Support\Collection}
     */
    public function generate(Product $product): array
    {
        return DB::transaction(function () use ($product) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $base = ProductUnit::weightKind($product->unit);

            if ($base === null) {
                throw new RuntimeException(sprintf(
                    'وحدة المنتج الأساسية "%s" ليست وحدة وزن. اضبطها على كغ أو غرام في بطاقة المنتج أولاً.',
                    $product->unit ?: 'غير محددة'
                ));
            }

            $converted = false;
            $priceRounded = false;

            if ($base === 'kg') {
                $priceRounded = $this->kiloPriceWouldRound($product);
                $this->convertToGrams($product);
                $product->refresh();
                $converted = true;
            }

            $this->ensureWeightUnits($product);

            return [
                'converted' => $converted,
                'price_rounded' => $priceRounded,
                'units' => $product->units()->orderByDesc('is_default')->orderBy('name')->get(),
            ];
        });
    }

    /**
     * Moves a product counted in kilos onto grams.
     *
     * Everything that counts the product is multiplied by 1000 and every
     * per-unit price divided by 1000, in the same transaction, so stock, its
     * FIFO cost layers and its movement trail keep agreeing with each other.
     */
    private function convertToGrams(Product $product): void
    {
        $factor = ProductUnit::GRAMS_PER_KILO;
        $units = $product->units()->get();

        // A unit that held several kilos would need a price multiplier in the
        // thousands, beyond what the column holds. Refused before anything moves.
        $tooLarge = $units->first(fn (ProductUnit $unit) => $unit->weight() === null
            && (float) $unit->price_multiplier * $factor > self::MAX_PRICE_MULTIPLIER);
        if ($tooLarge) {
            throw new RuntimeException(sprintf(
                'الوحدة "%s" كبيرة جداً لتحويلها إلى غرام. عدّلها أو احذفها ثم أعد المحاولة.',
                $tooLarge->name_ar ?: $tooLarge->name
            ));
        }

        foreach (self::SCALED as $table => [$counts, $prices]) {
            $this->scale($table, $table === 'products' ? 'id' : 'product_id', $product->id, $counts, $prices, $factor);
        }

        if ((int) $product->pack_quantity > 0) {
            DB::table('products')->where('id', $product->id)->update(['pack_quantity' => DB::raw('pack_quantity * '.$factor)]);
        }

        DB::table('products')->where('id', $product->id)->update(['unit' => self::GRAM_UNIT]);

        // Units other than the two weights — a roll, a sack — hold the same
        // weight as before, now counted in grams.
        foreach ($units as $unit) {
            if ($unit->weight() === null) {
                $unit->update([
                    'base_unit_multiplier' => ProductUnit::stockFactorOf($unit->base_unit_multiplier) * $factor,
                    'price_multiplier' => round((float) $unit->price_multiplier * $factor, 4),
                ]);
            }
        }

        $kilo = $this->kiloUnit($product);

        // Sale lines keep the price they were sold at, per the unit they were
        // sold in. A line in base units sold kilos, so it now names the kilo;
        // a line in another unit keeps it, and only its size is restated.
        foreach (self::SALE_LINES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $lines = DB::table($table)->where('product_id', $product->id)->get(['id', 'unit_multiplier', 'product_unit_id']);

            foreach ($lines as $line) {
                $oldFactor = ProductUnit::stockFactorOf($line->unit_multiplier);
                $ownUnit = $line->product_unit_id ? $units->firstWhere('id', (int) $line->product_unit_id) : null;
                $soldKilos = $oldFactor === 1 && ($ownUnit === null || $ownUnit->weight() !== null);

                DB::table($table)->where('id', $line->id)->update($soldKilos
                    ? ['product_unit_id' => $kilo->id, 'unit_name' => $kilo->name_ar, 'unit_multiplier' => $factor]
                    : ['unit_multiplier' => $oldFactor * $factor]);
            }
        }
    }

    /** The two weight units, created or corrected, with the kilo as default. */
    private function ensureWeightUnits(Product $product): void
    {
        $gram = $product->units()->get()->first(fn (ProductUnit $unit) => $unit->weight() === 'g')
            ?? new ProductUnit(['product_id' => $product->id]);

        $gram->fill([
            'name' => 'g',
            'name_ar' => self::GRAM_UNIT,
            'base_unit_multiplier' => 1,
            'price_multiplier' => 1,
            'is_default' => false,
        ])->save();

        $kilo = $this->kiloUnit($product);

        $product->units()->where('id', '!=', $kilo->id)->update(['is_default' => false]);
        $kilo->update(['is_default' => true]);
    }

    /** The product's كغ unit — the one already there, or a new one. */
    private function kiloUnit(Product $product): ProductUnit
    {
        $kilo = $product->units()->get()->first(fn (ProductUnit $unit) => $unit->weight() === 'kg')
            ?? new ProductUnit(['product_id' => $product->id, 'is_default' => false]);

        $kilo->fill([
            'name' => 'kg',
            'name_ar' => 'كغ',
            'base_unit_multiplier' => ProductUnit::GRAMS_PER_KILO,
            'price_multiplier' => ProductUnit::GRAMS_PER_KILO,
        ])->save();

        return $kilo;
    }

    /**
     * Whether the gram price, stored to five decimals, would no longer give
     * back the kilo price exactly — 3.657 / kg is 0.003657 / g, kept as 0.00366.
     */
    private function kiloPriceWouldRound(Product $product): bool
    {
        foreach (['price', 'cost_price'] as $column) {
            $kilo = (float) $product->{$column};
            if (abs(round($kilo / ProductUnit::GRAMS_PER_KILO, 5) * ProductUnit::GRAMS_PER_KILO - $kilo) > 0.000001) {
                return true;
            }
        }

        return false;
    }

    /** Multiplies counts and divides per-unit prices for one product in one table. */
    private function scale(string $table, string $key, int $productId, array $counts, array $prices, int $factor): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $changes = [];
        foreach ($counts as $column) {
            if (Schema::hasColumn($table, $column)) {
                $changes[$column] = DB::raw("{$column} * {$factor}");
            }
        }
        foreach ($prices as $column) {
            if (Schema::hasColumn($table, $column)) {
                $changes[$column] = DB::raw("ROUND({$column} / {$factor}.0, 5)");
            }
        }

        if ($changes !== []) {
            DB::table($table)->where($key, $productId)->update($changes);
        }
    }
}
