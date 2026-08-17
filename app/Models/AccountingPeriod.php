<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stretch of time the books are either still open to, or finished with.
 *
 * Closing one is what stops a month that has already been reported on from
 * quietly changing underneath the statements taken from it.
 */
class AccountingPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'reopened_at',
        'reopened_by',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * The closed period a date falls inside, if any.
     *
     * A date covered by no period at all is open: this has to be safe to add to
     * a system with years of history, so nothing is refused until somebody
     * actually closes the period it belongs to.
     */
    public static function closedFor(string $date): ?self
    {
        return static::query()
            ->where('status', self::STATUS_CLOSED)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /** Whether posting into this date is refused. */
    public static function isClosed(string $date): bool
    {
        return static::closedFor($date) !== null;
    }
}
