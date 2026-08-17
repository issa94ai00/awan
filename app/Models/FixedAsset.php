<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something bought to keep and use, not to sell.
 *
 * Its cost belongs to every period that uses it, not to the month it was paid
 * for. Straight-line: the depreciable amount — cost less whatever it is
 * expected to be worth at the end — divided evenly across its useful life.
 */
class FixedAsset extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'asset_number',
        'name',
        'category',
        'acquired_on',
        'cost',
        'salvage_value',
        'useful_life_months',
        'accumulated_depreciation',
        'depreciated_through',
        'status',
        'disposed_on',
        'disposal_proceeds',
        'warehouse_id',
        'supplier_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'acquired_on' => 'date',
        'depreciated_through' => 'date',
        'disposed_on' => 'date',
        'cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'disposal_proceeds' => 'decimal:2',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISPOSED = 'disposed';

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** What the asset is still carried at: cost less what has been used up. */
    public function netBookValue(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 2);
    }

    /** The part of the cost that is ever charged to expense. */
    public function depreciableAmount(): float
    {
        return round(max(0, (float) $this->cost - (float) $this->salvage_value), 2);
    }

    /**
     * One month's charge.
     *
     * Rounded per month rather than derived from an unrounded rate, so the
     * schedule is made of figures that were actually posted. The final month
     * takes whatever rounding left behind — see `monthlyChargeOn`.
     */
    public function monthlyCharge(): float
    {
        if ($this->useful_life_months <= 0) {
            return 0.0;
        }

        return round($this->depreciableAmount() / $this->useful_life_months, 2);
    }

    /**
     * What to charge for a given month, never taking the asset below its
     * salvage value.
     *
     * The last instalment absorbs the rounding difference: twelve charges of
     * 83.33 leave four cents of an asset that costs 1,000, and an asset that
     * never quite finishes depreciating would be charged forever.
     */
    public function chargeFor(Carbon $month): float
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return 0.0;
        }

        // Nothing is charged for a month that ended before the asset arrived.
        if ($month->copy()->endOfMonth()->lt($this->acquired_on)) {
            return 0.0;
        }

        $remaining = round($this->depreciableAmount() - (float) $this->accumulated_depreciation, 2);

        if ($remaining <= 0) {
            return 0.0;
        }

        return min($this->monthlyCharge(), $remaining);
    }

    /** Whether this month has already been charged. */
    public function isDepreciatedThrough(Carbon $month): bool
    {
        return $this->depreciated_through !== null
            && $this->depreciated_through->gte($month->copy()->endOfMonth()->startOfDay());
    }

    /** The key the acquisition entry is posted under. */
    public function acquisitionKey(): string
    {
        return 'fixed_asset:'.$this->id;
    }

    public function depreciationKey(Carbon $month): string
    {
        return 'depreciation:'.$this->id.':'.$month->format('Y-m');
    }

    public function disposalKey(): string
    {
        return 'asset_disposal:'.$this->id;
    }
}
