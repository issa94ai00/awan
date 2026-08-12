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

    /* ------------------------------------------------------------------ *
     * Availability
     * ------------------------------------------------------------------ */

    /**
     * What "available" means, in SQL, once and for the whole system.
     *
     * A warehouse row keeps its units in condition buckets that add up to
     * `quantity`:
     *
     *     quantity = available_quantity + damaged_quantity + quarantined_quantity
     *
     * and `reserved_quantity` is the part of `available_quantity` already
     * promised to a confirmed order. So the units that may actually be sold are
     * `available_quantity - reserved_quantity`, and nothing else.
     *
     * This existed in four different forms, each giving a different answer the
     * moment anything was reserved or damaged:
     *
     *     available_quantity - reserved_quantity   what the sell gate enforces
     *     quantity - reserved - damaged - quarantined   the inventory screen
     *     quantity - reserved_quantity             the WMS balance screen
     *     available_quantity                       assignments, MRP, composites
     *
     * The last two overstate: they count units that are damaged, quarantined or
     * already promised elsewhere, so a screen offered stock that the sell gate
     * would then refuse. The second agrees with the first only while the bucket
     * invariant holds, and CompositeProductService moves `available_quantity`
     * without `quantity`, which breaks it.
     *
     * Derived from `available_quantity` because that is the bucket the reserve
     * and issue paths actually test, so what a screen shows is exactly what the
     * system will let go out of the door. Clamped at zero: a negative balance is
     * a fault to be repaired (see `inventory:check`), never a sellable quantity.
     * Written as CASE rather than GREATEST so MySQL and SQLite both take it.
     */
    public static function availableSql(string $table = 'warehouse_inventory'): string
    {
        return "CASE WHEN {$table}.available_quantity - {$table}.reserved_quantity > 0"
            ." THEN {$table}.available_quantity - {$table}.reserved_quantity ELSE 0 END";
    }

    /** Adds the availability of each row as an `available` column. */
    public function scopeWithAvailable($query, string $table = 'warehouse_inventory')
    {
        return $query->selectRaw(self::availableSql($table).' as available');
    }

    /** The same figure for a loaded row. Kept in step with `availableSql()`. */
    public function getAvailableStockAttribute(): int
    {
        return max(0, (int) $this->available_quantity - (int) $this->reserved_quantity);
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

    /**
     * Rows at or below their reorder point.
     *
     * Compared `quantity` — the gross shelf count including damaged, quarantined
     * and reserved units — against the reorder point, while `isBelowReorderPoint()`
     * on the same model compared the available figure. The two disagreed about
     * the same row, and this one was the optimistic answer.
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('('.self::availableSql().') <= COALESCE(reorder_point, 0)');
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
