<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BinAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_warehouse_assignment_id',
        'bin_id',
        'is_primary',
        'priority_order',
        'capacity_percentage',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'priority_order' => 'integer',
        'capacity_percentage' => 'decimal:2',
    ];

    public function assignment()
    {
        return $this->belongsTo(ProductWarehouseAssignment::class, 'product_warehouse_assignment_id');
    }

    public function bin()
    {
        return $this->belongsTo(WarehouseBin::class);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeSecondary($query)
    {
        return $query->where('is_primary', false);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority_order', 'asc');
    }

    public function scopeForBin($query, $binId)
    {
        return $query->where('bin_id', $binId);
    }

    public function getAvailableCapacityAttribute(): float
    {
        if (!$this->bin) {
            return 0;
        }

        $totalCapacity = $this->bin->capacity_value;
        $allocatedCapacity = ($this->capacity_percentage / 100) * $totalCapacity;
        $currentUtilization = $this->bin->current_utilization;

        return max(0, $allocatedCapacity - $currentUtilization);
    }

    public function canAcceptQuantity($quantity): bool
    {
        return $this->available_capacity >= $quantity;
    }
}
