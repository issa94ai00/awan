<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RmaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'rma_request_id',
        'sales_order_item_id',
        'product_id',
        'product_variant_id',
        'quantity_requested',
        'quantity_received',
        'condition',
        'resolution',
        'exchange_product_id',
        'exchange_variant_id',
        'refund_amount',
        'notes',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
    ];

    const CONDITION_NEW = 'new';
    const CONDITION_USED = 'used';
    const CONDITION_DAMAGED = 'damaged';
    const CONDITION_MISSING = 'missing';

    const RESOLUTION_REFUND = 'refund';
    const RESOLUTION_EXCHANGE = 'exchange';
    const RESOLUTION_REPAIR = 'repair';
    const RESOLUTION_DISCARD = 'discard';

    public function rmaRequest()
    {
        return $this->belongsTo(RmaRequest::class);
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

    public function exchangeProduct()
    {
        return $this->belongsTo(Product::class, 'exchange_product_id');
    }

    public function exchangeVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'exchange_variant_id');
    }

    public function getConditionTextAttribute(): string
    {
        return match($this->condition) {
            self::CONDITION_NEW => 'جديد',
            self::CONDITION_USED => 'مستعمل',
            self::CONDITION_DAMAGED => 'تالف',
            self::CONDITION_MISSING => 'مفقود',
            default => $this->condition,
        };
    }

    public function getResolutionTextAttribute(): string
    {
        return match($this->resolution) {
            self::RESOLUTION_REFUND => 'استرداد',
            self::RESOLUTION_EXCHANGE => 'تبديل',
            self::RESOLUTION_REPAIR => 'إصلاح',
            self::RESOLUTION_DISCARD => 'تخلص',
            default => $this->resolution,
        };
    }

    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_requested;
    }

    public function isExchange(): bool
    {
        return $this->resolution === self::RESOLUTION_EXCHANGE;
    }

    public function isRefund(): bool
    {
        return $this->resolution === self::RESOLUTION_REFUND;
    }

    public function markAsReceived($quantity): void
    {
        $this->quantity_received = min($quantity, $this->quantity_requested);
        $this->save();
    }

    public function calculateRefundAmount(float $originalPrice): float
    {
        $multiplier = match($this->condition) {
            self::CONDITION_NEW => 1.0,
            self::CONDITION_USED => 0.7,
            self::CONDITION_DAMAGED => 0.5,
            self::CONDITION_MISSING => 0.0,
            default => 0.5,
        };

        return $originalPrice * $multiplier * $this->quantity_requested;
    }
}
