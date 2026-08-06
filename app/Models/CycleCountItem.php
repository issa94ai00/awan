<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cycle_count_id',
        'product_id',
        'product_variant_id',
        'bin_id',
        'expected_quantity',
        'counted_quantity',
        'variance',
        'unit_cost',
        'variance_value',
        'variance_reason',
        'verified',
        'notes',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'variance_value' => 'decimal:2',
        'verified' => 'boolean',
    ];

    const VARIANCE_REASON_THEFT = 'theft';
    const VARIANCE_REASON_DAMAGE = 'damage';
    const VARIANCE_REASON_DATA_ENTRY = 'data_entry';
    const VARIANCE_REASON_UNKNOWN = 'unknown';

    public function cycleCount()
    {
        return $this->belongsTo(CycleCount::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function bin()
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByBin($query, $binId)
    {
        return $query->where('bin_id', $binId);
    }

    public function scopeHasVariance($query)
    {
        return $query->where('variance', '!=', 0);
    }

    public function getVarianceReasonTextAttribute(): string
    {
        return match($this->variance_reason) {
            self::VARIANCE_REASON_THEFT => 'سرقة',
            self::VARIANCE_REASON_DAMAGE => 'تلف',
            self::VARIANCE_REASON_DATA_ENTRY => 'خطأ في الإدخال',
            self::VARIANCE_REASON_UNKNOWN => 'غير معروف',
            default => $this->variance_reason,
        };
    }

    public function hasVariance(): bool
    {
        return $this->variance !== 0;
    }

    public function isOverage(): bool
    {
        return $this->variance > 0;
    }

    public function isShortage(): bool
    {
        return $this->variance < 0;
    }

    public function calculateVariance(): void
    {
        $this->variance = $this->counted_quantity - $this->expected_quantity;
        $this->variance_value = abs($this->variance) * ($this->unit_cost ?? 0);
        $this->save();
    }

    public function verify(): void
    {
        $this->verified = true;
        $this->save();
    }
}
