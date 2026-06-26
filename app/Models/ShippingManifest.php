<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingManifest extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'manifest_number',
        'carrier_id',
        'carrier_name',
        'status',
        'shipping_date',
        'estimated_delivery',
        'actual_delivery',
        'total_packages',
        'total_weight',
        'shipping_cost',
        'tracking_number',
        'route',
        'driver_id',
        'notes',
    ];

    protected $casts = [
        'route' => 'array',
        'shipping_date' => 'date',
        'estimated_delivery' => 'date',
        'actual_delivery' => 'date',
        'total_weight' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function items()
    {
        return $this->hasMany(ShippingManifestItem::class);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCarrier($query, $carrierId)
    {
        return $query->where('carrier_id', $carrierId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', self::STATUS_IN_TRANSIT);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_IN_TRANSIT => 'قيد الشحن',
            self::STATUS_DELIVERED => 'تم التسليم',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    public function isInTransit(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function markAsInTransit(): void
    {
        $this->status = self::STATUS_IN_TRANSIT;
        $this->save();
    }

    public function markAsDelivered(): void
    {
        $this->status = self::STATUS_DELIVERED;
        $this->actual_delivery = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    public function generateManifestNumber(): string
    {
        return 'SHP-' . str_pad($this->id ?? ShippingManifest::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
