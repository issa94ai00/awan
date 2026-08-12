<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ProductWarehouseAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'effective_date',
        'expiry_date',
        'is_active',
        'replenishment_method',
        'planning_method',
        'min_stock_level',
        'max_stock_level',
        'safety_stock',
        'supplier_id',
        'lead_time_days',
        'primary_bin_id',
        'putaway_strategy',
        'auto_reorder_enabled',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'auto_reorder_enabled' => 'boolean',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer',
        'safety_stock' => 'integer',
        'lead_time_days' => 'integer',
    ];

    // Replenishment Methods
    const REPLENISHMENT_PURCHASE = 'purchase';

    const REPLENISHMENT_MANUFACTURE = 'manufacture';

    const REPLENISHMENT_INTERNAL_DISTRIBUTION = 'internal_distribution';

    const REPLENISHMENT_WAREHOUSE_TRANSFER = 'warehouse_transfer';

    // Planning Methods
    const PLANNING_ROP = 'rop';

    const PLANNING_MRP = 'mrp';

    // Putaway Strategies
    const PUTAWAY_FIFO = 'fifo';

    const PUTAWAY_FEFO = 'fefo';

    const PUTAWAY_SIMILARITY = 'similarity';

    const PUTAWAY_WEIGHT_BASED = 'weight_based';

    const PUTAWAY_VOLUME_BASED = 'volume_based';

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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function primaryBin()
    {
        return $this->belongsTo(WarehouseBin::class, 'primary_bin_id');
    }

    public function binAssignments()
    {
        return $this->hasMany(BinAssignment::class);
    }

    public function inventory()
    {
        return $this->hasMany(WarehouseInventory::class, 'product_id', 'product_id')
            ->where('warehouse_id', $this->warehouse_id);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('effective_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now());
            });
    }

    public function scopeForWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Filters assignments by their available stock.
     *
     * `available_stock` is an accessor that sums the warehouse rows behind the
     * assignment — there is no such column. Callers were passing it straight to
     * `where()`, which SQL rejects outright ("Unknown column 'available_stock'"),
     * so every path that looked for a warehouse able to cover a quantity threw
     * instead of answering. This is the same sum expressed as a correlated
     * subquery, so the filter and the accessor can never drift apart.
     */
    public function scopeWhereAvailableStock($query, string $operator, $value)
    {
        // The operator lands in raw SQL, so it is matched against a fixed list
        // rather than trusted — a comparison operator is never user input here,
        // and this keeps it impossible for it to become one.
        if (! in_array($operator, ['=', '!=', '<', '<=', '>', '>='], true)) {
            throw new \InvalidArgumentException('عامل مقارنة غير مدعوم: '.$operator);
        }

        return $query->whereRaw(
            '(SELECT COALESCE(SUM('.WarehouseInventory::availableSql('wi').'), 0)
                FROM warehouse_inventory wi
               WHERE wi.product_id = product_warehouse_assignments.product_id
                 AND wi.warehouse_id = product_warehouse_assignments.warehouse_id) '
            .$operator.' ?',
            [$value]
        );
    }

    public function scopeFutureDated($query)
    {
        return $query->where('effective_date', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function getReplenishmentMethodTextAttribute(): string
    {
        return match ($this->replenishment_method) {
            self::REPLENISHMENT_PURCHASE => 'شراء',
            self::REPLENISHMENT_MANUFACTURE => 'تصنيع',
            self::REPLENISHMENT_INTERNAL_DISTRIBUTION => 'توزيع داخلي',
            self::REPLENISHMENT_WAREHOUSE_TRANSFER => 'نقل مخزني',
            default => $this->replenishment_method,
        };
    }

    public function getPlanningMethodTextAttribute(): string
    {
        return match ($this->planning_method) {
            self::PLANNING_ROP => 'نقطة إعادة طلب',
            self::PLANNING_MRP => 'تخطيط متطلبات المواد',
            default => $this->planning_method,
        };
    }

    public function getCurrentStockAttribute(): int
    {
        return $this->inventory()->sum('quantity');
    }

    /**
     * Units of this product the warehouse can actually give.
     *
     * Was `SUM(available_quantity)`, which counts stock already promised to
     * confirmed orders. Every caller — the composite-product check, the MRP
     * shortfall, "which warehouse can cover this?" — was therefore told about
     * units it could not have, and the sell gate refused them later.
     */
    public function getAvailableStockAttribute(): int
    {
        return (int) $this->inventory()->sum(
            DB::raw(WarehouseInventory::availableSql())
        );
    }

    public function isBelowMinStock(): bool
    {
        return $this->available_stock < $this->min_stock_level;
    }

    public function isBelowReorderPoint(): bool
    {
        return $this->available_stock < ($this->safety_stock + $this->min_stock_level);
    }

    public function calculateReorderQuantity(): int
    {
        $targetStock = $this->max_stock_level ?? ($this->min_stock_level * 2);

        return $targetStock - $this->available_stock;
    }

    public function calculateDistanceFromSupplier(): ?float
    {
        if (! $this->supplier || ! $this->warehouse) {
            return null;
        }

        if (! $this->supplier->latitude || ! $this->supplier->longitude ||
            ! $this->warehouse->latitude || ! $this->warehouse->longitude) {
            return null;
        }

        $earthRadius = 6371; // km
        $latFrom = deg2rad($this->supplier->latitude);
        $lonFrom = deg2rad($this->supplier->longitude);
        $latTo = deg2rad($this->warehouse->latitude);
        $lonTo = deg2rad($this->warehouse->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function getRecommendedBinForPutaway($product): ?WarehouseBin
    {
        $strategy = $this->putaway_strategy;
        $warehouse = $this->warehouse;

        switch ($strategy) {
            case self::PUTAWAY_WEIGHT_BASED:
                return $warehouse->bins()
                    ->active()
                    ->where('max_weight', '>=', $product->weight ?? 0)
                    ->orderBy('current_utilization', 'asc')
                    ->first();

            case self::PUTAWAY_VOLUME_BASED:
                $productVolume = ($product->length ?? 0) * ($product->width ?? 0) * ($product->height ?? 0);

                return $warehouse->bins()
                    ->active()
                    ->where('capacity_type', 'volume')
                    ->where('capacity_value', '>=', $productVolume)
                    ->orderBy('current_utilization', 'asc')
                    ->first();

            case self::PUTAWAY_SIMILARITY:
                // Find bins with similar products (same category)
                return $warehouse->bins()
                    ->active()
                    ->whereHas('inventory', function ($q) use ($product) {
                        $q->whereHas('product', function ($subQ) use ($product) {
                            $subQ->where('category_id', $product->category_id);
                        });
                    })
                    ->orderBy('current_utilization', 'asc')
                    ->first();

            case self::PUTAWAY_FEFO:
                // For FEFO, prioritize bins with products expiring soon
                return $warehouse->bins()
                    ->active()
                    ->whereHas('inventory', function ($q) {
                        $q->orderBy('expiry_date', 'asc');
                    })
                    ->first();

            case self::PUTAWAY_FIFO:
            default:
                // Default FIFO: use primary bin or least utilized bin
                if ($this->primaryBin && ! $this->primaryBin->isFull()) {
                    return $this->primaryBin;
                }

                return $warehouse->bins()
                    ->active()
                    ->where('type', WarehouseBin::TYPE_STORAGE)
                    ->orderBy('current_utilization', 'asc')
                    ->first();
        }
    }
}
