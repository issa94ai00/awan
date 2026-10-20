<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
    use HasFactory;

    /** A kilogram in grams — the factor between the two weight units. */
    public const GRAMS_PER_KILO = 1000;

    /** Names a product's unit may carry when it is counted in grams or in kilos. */
    private const GRAM_NAMES = ['g', 'gr', 'gram', 'grams', 'غ', 'غرام', 'جرام', 'غم'];
    private const KILO_NAMES = ['kg', 'kgs', 'kilo', 'kilogram', 'kilograms', 'كغ', 'كيلو', 'كيلوغرام', 'كيلوجرام', 'كغم'];

    protected $fillable = [
        'product_id',
        'name',
        'name_ar',
        'barcode',
        'base_unit_multiplier',
        'price_multiplier',
        'is_default',
    ];

    protected $casts = [
        // Whole base units: stock is counted in integers, so a unit holding
        // half a base unit could not be taken off a shelf.
        'base_unit_multiplier' => 'integer',
        'price_multiplier' => 'decimal:4',
        'is_default' => 'boolean',
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Base units of stock that one unit with this multiplier holds.
     *
     * Never below one: a line with no unit, or a unit saved before factors
     * were checked (one gram unit went in as 0), sells one base unit each.
     */
    public static function stockFactorOf($multiplier): int
    {
        return max(1, (int) round((float) ($multiplier ?? 1)));
    }

    /** 'g' or 'kg' when a unit name means grams or kilos, otherwise null. */
    public static function weightKind(?string $name): ?string
    {
        $name = mb_strtolower(trim((string) $name));

        return match (true) {
            in_array($name, self::GRAM_NAMES, true) => 'g',
            in_array($name, self::KILO_NAMES, true) => 'kg',
            default => null,
        };
    }

    /** 'g' or 'kg' when this unit is a weight unit by either of its names. */
    public function weight(): ?string
    {
        return self::weightKind($this->name) ?? self::weightKind($this->name_ar);
    }
}
