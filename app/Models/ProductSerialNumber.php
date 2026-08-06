<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSerialNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'batch_id',
        'serial_number',
        'status',
        'sale_order_id',
        'sale_order_item_id',
        'sold_at',
        'reserved_at',
        'notes',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'reserved_at' => 'datetime',
    ];

    const STATUS_IN_STOCK = 'in_stock';
    const STATUS_RESERVED = 'reserved';
    const STATUS_SOLD = 'sold';
    const STATUS_DAMAGED = 'damaged';
    const STATUS_LOST = 'lost';
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

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function saleOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sale_order_id');
    }

    public function saleOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class, 'sale_order_item_id');
    }

    public function reserve($orderId = null, $orderItemId = null): bool
    {
        if ($this->status !== self::STATUS_IN_STOCK) {
            return false;
        }

        $this->status = self::STATUS_RESERVED;
        $this->reserved_at = now();
        $this->sale_order_id = $orderId;
        $this->sale_order_item_id = $orderItemId;
        $this->save();

        return true;
    }

    public function markAsSold(): void
    {
        $this->status = self::STATUS_SOLD;
        $this->sold_at = now();
        $this->save();
    }

    public function markAsDamaged(): void
    {
        $this->status = self::STATUS_DAMAGED;
        $this->save();
    }

    public function markAsLost(): void
    {
        $this->status = self::STATUS_LOST;
        $this->save();
    }

    public function markAsQuarantined(): void
    {
        $this->status = self::STATUS_QUARANTINED;
        $this->save();
    }

    public function releaseReservation(): void
    {
        $this->status = self::STATUS_IN_STOCK;
        $this->reserved_at = null;
        $this->sale_order_id = null;
        $this->sale_order_item_id = null;
        $this->save();
    }

    public function scopeInStock($query)
    {
        return $query->where('status', self::STATUS_IN_STOCK);
    }

    public function scopeReserved($query)
    {
        return $query->where('status', self::STATUS_RESERVED);
    }

    public function scopeSold($query)
    {
        return $query->where('status', self::STATUS_SOLD);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeBySerialNumber($query, $serialNumber)
    {
        return $query->where('serial_number', $serialNumber);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_IN_STOCK => 'في المخزون',
            self::STATUS_RESERVED => 'محجوز',
            self::STATUS_SOLD => 'تم البيع',
            self::STATUS_DAMAGED => 'تالف',
            self::STATUS_LOST => 'مفقود',
            self::STATUS_QUARANTINED => 'معزول',
            default => $this->status,
        };
    }
}
