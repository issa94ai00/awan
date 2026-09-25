<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function warehouseInventories()
    {
        return $this->hasMany(WarehouseInventory::class);
    }
}
