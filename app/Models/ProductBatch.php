<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity',
        'quantity_reserved',
        'unit_cost',
        'status',
    ];

    protected $casts = [
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'quantity' => 'integer',
        'quantity_reserved' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    const STATUS_AVAILABLE = 'available';
    const STATUS_RESERVED = 'reserved';
    const STATUS_EXPIRED = 'expired';
    const STATUS_QUARANTINED = 'quarantined';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function serialNumbers()
    {
        return $this->hasMany(ProductSerialNumber::class, 'batch_id');
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->quantity_reserved;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function isExpiringSoon($days = 30): bool
    {
        return $this->expiry_date->lte(now()->addDays($days)) && !$this->isExpired();
    }

    public function reserve($quantity): bool
    {
        if ($this->available_quantity < $quantity) {
            return false;
        }

        $this->quantity_reserved += $quantity;
        $this->status = self::STATUS_RESERVED;
        $this->save();

        return true;
    }

    public function release($quantity): void
    {
        $this->quantity_reserved = max(0, $this->quantity_reserved - $quantity);
        
        if ($this->quantity_reserved === 0) {
            $this->status = self::STATUS_AVAILABLE;
        }
        
        $this->save();
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)
            ->where('expiry_date', '>', now());
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_AVAILABLE => 'متاح',
            self::STATUS_RESERVED => 'محجوز',
            self::STATUS_EXPIRED => 'منتهي الصلاحية',
            self::STATUS_QUARANTINED => 'معزول',
            default => $this->status,
        };
    }
}
