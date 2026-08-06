<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickingListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'picking_list_id',
        'sales_order_item_id',
        'product_id',
        'product_variant_id',
        'bin_id',
        'quantity_to_pick',
        'quantity_picked',
        'status',
        'barcode',
        'verified',
        'picked_at',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'picked_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PICKED = 'picked';
    const STATUS_SHORT = 'short';
    const STATUS_CANCELLED = 'cancelled';

    public function pickingList()
    {
        return $this->belongsTo(PickingList::class);
    }

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class);
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

    public function packingListItems()
    {
        return $this->hasMany(PackingListItem::class);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePicked($query)
    {
        return $query->where('status', self::STATUS_PICKED);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_PICKED => 'تم الاختيار',
            self::STATUS_SHORT => 'نقص',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    public function isFullyPicked(): bool
    {
        return $this->quantity_picked >= $this->quantity_to_pick;
    }

    public function isShort(): bool
    {
        return $this->quantity_picked < $this->quantity_to_pick && $this->quantity_picked > 0;
    }

    public function markAsPicked($quantity): void
    {
        $this->quantity_picked = min($quantity, $this->quantity_to_pick);
        $this->status = $this->isFullyPicked() ? self::STATUS_PICKED : self::STATUS_SHORT;
        $this->picked_at = now();
        $this->save();
    }

    public function verify(): void
    {
        $this->verified = true;
        $this->save();
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity_to_pick - $this->quantity_picked);
    }
}
