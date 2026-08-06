<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseBin extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'code',
        'bin_code',
        'name',
        'zone',
        'rack',
        'aisle',
        'shelf',
        'level',
        'type',
        'capacity_type',
        'capacity_value',
        'current_utilization',
        'is_active',
        'requires_equipment',
        'dimensions',
        'coordinates',
        'notes',
        'max_weight',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'coordinates' => 'array',
        'is_active' => 'boolean',
        'requires_equipment' => 'boolean',
        'capacity_value' => 'decimal:2',
        'current_utilization' => 'decimal:2',
    ];

    const TYPE_STORAGE = 'storage';
    const TYPE_PICKING = 'picking';
    const TYPE_RECEIVING = 'receiving';
    const TYPE_SHIPPING = 'shipping';
    const TYPE_QUARANTINE = 'quarantine';
    const TYPE_RETURNS = 'returns';

    const CAPACITY_VOLUME = 'volume';
    const CAPACITY_WEIGHT = 'weight';
    const CAPACITY_COUNT = 'count';

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function inventory()
    {
        return $this->hasMany(WarehouseInventory::class, 'bin_id');
    }

    public function pickingListItems()
    {
        return $this->hasMany(PickingListItem::class, 'bin_id');
    }

    public function cycleCountItems()
    {
        return $this->hasMany(CycleCountItem::class, 'bin_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByZone($query, $zone)
    {
        return $query->where('zone', $zone);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopePicking($query)
    {
        return $query->where('type', self::TYPE_PICKING);
    }

    public function scopeStorage($query)
    {
        return $query->where('type', self::TYPE_STORAGE);
    }

    public function getTypeTextAttribute(): string
    {
        return match($this->type) {
            self::TYPE_STORAGE => 'تخزين',
            self::TYPE_PICKING => 'اختيار',
            self::TYPE_RECEIVING => 'استلام',
            self::TYPE_SHIPPING => 'شحن',
            self::TYPE_QUARANTINE => 'حجر صحي',
            self::TYPE_RETURNS => 'إرجاع',
            default => $this->type,
        };
    }

    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->capacity_value <= 0) {
            return 0;
        }
        return min(100, ($this->current_utilization / $this->capacity_value) * 100);
    }

    public function getRemainingCapacityAttribute(): float
    {
        return max(0, $this->capacity_value - $this->current_utilization);
    }

    public function isFull(): bool
    {
        return $this->current_utilization >= $this->capacity_value;
    }

    public function isEmpty(): bool
    {
        return $this->current_utilization <= 0;
    }

    public function getLocationCodeAttribute(): string
    {
        $parts = array_filter([
            $this->zone,
            $this->aisle,
            $this->shelf,
            $this->level,
        ]);

        return implode('-', $parts);
    }

    public function updateUtilization($amount): void
    {
        $this->current_utilization = max(0, $this->current_utilization + $amount);
        $this->save();
    }

    public function getTotalItemCount(): int
    {
        return $this->inventory()->sum('stock_quantity');
    }
}
