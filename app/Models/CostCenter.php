<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A part of the business a figure can be attributed to.
 *
 * Usually a branch or warehouse, which is why one is created for each — but
 * not only: an administration or a delivery fleet holds no stock and still
 * carries costs, and a dimension pinned to the warehouse table could not
 * represent them.
 */
class CostCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'warehouse_id',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * The centre a warehouse's postings belong to, if one claims it.
     *
     * Resolved per request rather than per line: a sale split across two
     * warehouses asks this once for each, and a goods issue of forty lines
     * would otherwise be forty queries for two answers.
     *
     * @var array<int,int|null>
     */
    private static array $byWarehouse = [];

    public static function forWarehouse(?int $warehouseId): ?int
    {
        if (! $warehouseId) {
            return null;
        }

        if (! array_key_exists($warehouseId, self::$byWarehouse)) {
            self::$byWarehouse[$warehouseId] = static::query()
                ->where('warehouse_id', $warehouseId)
                ->where('is_active', true)
                ->value('id');
        }

        return self::$byWarehouse[$warehouseId];
    }

    /** Clears the per-request cache; used when centres change under a test. */
    public static function forgetWarehouseCache(): void
    {
        self::$byWarehouse = [];
    }
}
