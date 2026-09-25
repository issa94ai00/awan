<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price',
        'cost_price',
        'stock_quantity',
        'color',
        'size',
        'material',
    ];

    protected $casts = [
        'price' => 'decimal:5',
        'cost_price' => 'decimal:5',
        'stock_quantity' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * What tells this variant apart from its siblings — "4\" / white" — as
     * shown after the product's name wherever a variant stands on its own.
     */
    public static function labelFor(?string $size, ?string $color, ?string $material): string
    {
        return collect([$size, $color, $material])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(' / ');
    }

    public function getLabelAttribute(): string
    {
        return self::labelFor($this->size, $this->color, $this->material);
    }

    /**
     * The product's name followed by this variant's label.
     */
    public function displayName(?string $productName): string
    {
        $label = $this->label;

        return $label !== '' ? trim((string) $productName).' - '.$label : (string) $productName;
    }

    /**
     * The variant's own price, or the product's when the variant has none (0).
     */
    public function sellingPrice(): float
    {
        return (float) $this->price > 0 ? (float) $this->price : (float) ($this->product->price ?? 0);
    }

    /**
     * The variants a document's lines name, checked against each line's
     * product: a line for the tap cannot carry a floor drain's size.
     *
     * @param  array<int|string, array<string, mixed>>  $items  lines with product_id and $key
     * @return \Illuminate\Support\Collection<int, self>  keyed by variant id
     *
     * @throws ValidationException
     */
    public static function forLines(array $items, string $key = 'product_variant_id', string $field = 'items')
    {
        $ids = collect($items)->pluck($key)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $variants = $ids->isEmpty() ? collect() : self::whereIn('id', $ids)->get()->keyBy('id');

        $errors = [];
        foreach ($items as $index => $item) {
            $variantId = (int) ($item[$key] ?? 0);
            if (! $variantId) {
                continue;
            }

            $variant = $variants->get($variantId);
            if (! $variant || (int) $variant->product_id !== (int) ($item['product_id'] ?? 0)) {
                $errors["{$field}.{$index}.{$key}"] = 'المتغير المحدد لا يتبع هذا المنتج';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $variants;
    }

    /**
     * Move this variant's own stock count with the goods.
     *
     * Warehouse stock, reservations and cost stay per product; this count is
     * what the store and the pickers read to tell the sizes apart, so a
     * receipt raises it and a shipment lowers it. It never goes below zero —
     * a count that was never entered is not a debt.
     */
    public static function adjustStockCount(?int $variantId, int $delta): void
    {
        if (! $variantId || $delta === 0) {
            return;
        }

        self::whereKey($variantId)->update([
            'stock_quantity' => DB::raw('CASE WHEN COALESCE(stock_quantity, 0) + ('.$delta.') < 0 THEN 0 ELSE COALESCE(stock_quantity, 0) + ('.$delta.') END'),
        ]);
    }

    public function warehouseInventories()
    {
        return $this->hasMany(WarehouseInventory::class);
    }
}
