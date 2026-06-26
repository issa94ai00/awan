<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseInventory extends Model
{
    use HasFactory;

    protected $table = 'warehouse_inventory';

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'reserved_quantity',
        'reorder_point',
        'safety_stock',
        'bin_id',
        'batch_number',
        'expiry_date',
        'serial_numbers',
        'cost_basis',
        'last_counted_at',
        'count_variance',
        'available_quantity',
        'damaged_quantity',
        'quarantined_quantity',
        'lead_time_days',
        'average_daily_sales',
        'last_reorder_at',
        'auto_reorder_enabled',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'reorder_point' => 'integer',
        'safety_stock' => 'integer',
        'expiry_date' => 'date',
        'serial_numbers' => 'array',
        'last_counted_at' => 'datetime',
        'count_variance' => 'integer',
        'available_quantity' => 'integer',
        'damaged_quantity' => 'integer',
        'quarantined_quantity' => 'integer',
        'lead_time_days' => 'integer',
        'average_daily_sales' => 'decimal:2',
        'last_reorder_at' => 'datetime',
        'auto_reorder_enabled' => 'boolean',
    ];

    const COST_BASIS_FIFO = 'FIFO';
    const COST_BASIS_FEFO = 'FEFO';
    const COST_BASIS_LIFO = 'LIFO';

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
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
        return $this->belongsTo(WarehouseBin::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function serialNumbers()
    {
        return $this->hasMany(ProductSerialNumber::class);
    }

    public function reorderAlerts()
    {
        return $this->hasMany(ReorderAlert::class);
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->quantity - $this->reserved_quantity - $this->damaged_quantity - $this->quarantined_quantity;
    }

    public function isBelowReorderPoint(): bool
    {
        return $this->available_stock < $this->reorder_point;
    }

    public function calculateDynamicReorderPoint(): int
    {
        $leadTime = $this->lead_time_days ?? 7;
        $dailySales = $this->average_daily_sales ?? 0;
        $safetyStock = $this->safety_stock ?? 0;
        
        return (int) ceil(($dailySales * $leadTime) + $safetyStock);
    }

    public function updateAverageDailySales(): void
    {
        $days = 30;
        $startDate = now()->subDays($days);
        
        $totalSold = StockMovement::where('product_id', $this->product_id)
            ->where('warehouse_id', $this->warehouse_id)
            ->where('movement_type', StockMovement::TYPE_OUT)
            ->where('created_at', '>=', $startDate)
            ->sum('quantity');
        
        $this->average_daily_sales = $totalSold / $days;
        $this->save();
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'reorder_point');
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }
}
