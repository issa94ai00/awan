<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'country',
        'manager_name',
        'manager_phone',
        'is_active',
        'location_type',
        'latitude',
        'longitude',
        'capacity',
        'operating_hours',
        'is_primary',
        'manager_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'operating_hours' => 'array',
    ];

    const TYPE_WAREHOUSE = 'warehouse';
    const TYPE_BRANCH = 'branch';
    const TYPE_DISTRIBUTION_CENTER = 'distribution_center';
    const TYPE_3PL = '3pl';

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'stock_movements');
    }

    public function manager(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function inventory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function bins(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WarehouseBin::class);
    }

    public function transfersFrom(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'from_warehouse_id');
    }

    public function transfersTo(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'to_warehouse_id');
    }

    public function batches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function serialNumbers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductSerialNumber::class);
    }

    public function reorderAlerts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReorderAlert::class);
    }

    public function pickingLists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PickingList::class);
    }

    public function packingLists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PackingList::class);
    }

    public function shippingManifests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShippingManifest::class);
    }

    public function cycleCounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CycleCount::class);
    }

    public function fulfillmentOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesOrder::class, 'fulfillment_warehouse_id');
    }

    public function getUtilizationPercentageAttribute(): float
    {
        if (!$this->capacity || $this->capacity <= 0) {
            return 0;
        }

        $totalStock = $this->inventory()->sum('quantity');
        return ($totalStock / $this->capacity) * 100;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('location_type', $type);
    }

    public function getLocationTypeTextAttribute(): string
    {
        return match($this->location_type) {
            self::TYPE_WAREHOUSE => 'مستودع',
            self::TYPE_BRANCH => 'فرع',
            self::TYPE_DISTRIBUTION_CENTER => 'مركز توزيع',
            self::TYPE_3PL => 'طرف ثالث',
            default => $this->location_type,
        };
    }
}
