<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingManifestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_manifest_id',
        'packing_list_id',
        'sales_order_id',
        'tracking_number',
        'package_number',
        'weight',
        'dimensions',
        'delivery_address',
        'recipient_name',
        'recipient_phone',
        'delivery_status',
        'delivered_at',
        'signature',
        'notes',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'weight' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    const DELIVERY_STATUS_PENDING = 'pending';
    const DELIVERY_STATUS_IN_TRANSIT = 'in_transit';
    const DELIVERY_STATUS_DELIVERED = 'delivered';
    const DELIVERY_STATUS_FAILED = 'failed';

    public function shippingManifest()
    {
        return $this->belongsTo(ShippingManifest::class);
    }

    public function packingList()
    {
        return $this->belongsTo(PackingList::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('delivery_status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('delivery_status', self::DELIVERY_STATUS_PENDING);
    }

    public function scopeDelivered($query)
    {
        return $query->where('delivery_status', self::DELIVERY_STATUS_DELIVERED);
    }

    public function getDeliveryStatusTextAttribute(): string
    {
        return match($this->delivery_status) {
            self::DELIVERY_STATUS_PENDING => 'معلق',
            self::DELIVERY_STATUS_IN_TRANSIT => 'قيد التوصيل',
            self::DELIVERY_STATUS_DELIVERED => 'تم التسليم',
            self::DELIVERY_STATUS_FAILED => 'فشل',
            default => $this->delivery_status,
        };
    }

    public function isDelivered(): bool
    {
        return $this->delivery_status === self::DELIVERY_STATUS_DELIVERED;
    }

    public function markAsDelivered($signature = null): void
    {
        $this->delivery_status = self::DELIVERY_STATUS_DELIVERED;
        $this->delivered_at = now();
        if ($signature) {
            $this->signature = $signature;
        }
        $this->save();
    }

    public function markAsFailed(): void
    {
        $this->delivery_status = self::DELIVERY_STATUS_FAILED;
        $this->save();
    }
}
