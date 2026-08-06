<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReorderAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'product_variant_id',
        'current_quantity',
        'reorder_point',
        'safety_stock',
        'suggested_order_quantity',
        'severity',
        'status',
        'alerted_at',
        'resolved_at',
        'resolved_by',
        'notes',
    ];

    protected $casts = [
        'alerted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_CRITICAL = 'critical';

    const STATUS_PENDING = 'pending';
    const STATUS_ORDERED = 'ordered';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_DISMISSED = 'dismissed';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function calculateSeverity(): void
    {
        $stockRatio = $this->current_quantity / $this->reorder_point;

        if ($stockRatio <= 0.25) {
            $this->severity = self::SEVERITY_CRITICAL;
        } elseif ($stockRatio <= 0.5) {
            $this->severity = self::SEVERITY_MEDIUM;
        } else {
            $this->severity = self::SEVERITY_LOW;
        }

        $this->save();
    }

    public function calculateSuggestedOrderQuantity(): void
    {
        $leadTime = 7; // Default lead time in days
        $dailySales = 1; // Default daily sales
        
        // Get actual values from warehouse inventory if available
        $inventory = WarehouseInventory::where('product_id', $this->product_id)
            ->where('warehouse_id', $this->warehouse_id)
            ->first();

        if ($inventory) {
            $leadTime = $inventory->lead_time_days ?? 7;
            $dailySales = max(1, $inventory->average_daily_sales ?? 1);
        }

        $targetStock = ($dailySales * $leadTime) + $this->safety_stock;
        $this->suggested_order_quantity = (int) ceil($targetStock - $this->current_quantity);
        
        $this->save();
    }

    public function markAsOrdered($userId = null): void
    {
        $this->status = self::STATUS_ORDERED;
        $this->resolved_by = $userId;
        $this->save();
    }

    public function markAsResolved($userId): void
    {
        $this->status = self::STATUS_RESOLVED;
        $this->resolved_at = now();
        $this->resolved_by = $userId;
        $this->save();
    }

    public function dismiss($userId): void
    {
        $this->status = self::STATUS_DISMISSED;
        $this->resolved_at = now();
        $this->resolved_by = $userId;
        $this->save();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_ORDERED => 'تم الطلب',
            self::STATUS_RESOLVED => 'تم الحل',
            self::STATUS_DISMISSED => 'متجاهل',
            default => $this->status,
        };
    }

    public function getSeverityTextAttribute(): string
    {
        return match($this->severity) {
            self::SEVERITY_LOW => 'منخفض',
            self::SEVERITY_MEDIUM => 'متوسط',
            self::SEVERITY_CRITICAL => 'حرج',
            default => $this->severity,
        };
    }
}
