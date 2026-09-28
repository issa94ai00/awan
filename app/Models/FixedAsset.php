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
        'cost' => 'decimal:5',
        'salvage_value' => 'decimal:5',
        'accumulated_depreciation' => 'decimal:5',
        'disposal_proceeds' => 'decimal:5',
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
     * takes whatever rounding left behind — see FixedAssetDepreciation::owed.
     */
    public function monthlyCharge(): float
    {
        if ($this->useful_life_months <= 0) {
            return 0.0;
        }

        return round($this->depreciableAmount() / $this->useful_life_months, 2);
    }

    /** Whether everything above the salvage value has been charged. */
    public function isFullyDepreciated(): bool
    {
        return round($this->depreciableAmount() - (float) $this->accumulated_depreciation, 2) <= 0;
    }

    /** Monthly charges still to come before the asset reaches its salvage value. */
    public function remainingMonths(): int
    {
        $remaining = round($this->depreciableAmount() - (float) $this->accumulated_depreciation, 2);
        $monthly = $this->monthlyCharge();

        if ($remaining <= 0 || $monthly <= 0) {
            return 0;
        }

        return (int) ceil(round($remaining / $monthly, 6));
    }

    /**
     * The month the last charge falls in, at the current pace.
     *
     * Counted from the month after the last one charged, so an asset the run
     * is behind on shows when it will really finish, not when it should have.
     */
    public function lastChargeMonth(): ?string
    {
        $remaining = $this->remainingMonths();

        if ($remaining === 0 || ! $this->acquired_on) {
            return null;
        }

        $next = $this->depreciated_through
            ? $this->depreciated_through->copy()->addDay()->startOfMonth()
            : $this->acquired_on->copy()->startOfMonth();

        return $next->addMonthsNoOverflow($remaining - 1)->format('Y-m');
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
