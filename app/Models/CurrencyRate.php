<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What one unit of the base currency was worth, and from when.
 *
 * Append-only: a wrong rate is corrected by entering a new one, never by
 * editing the old. Orders and reports read the rate that was in force at their
 * own moment, so rewriting a past rate would silently restate history.
 */
class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency_id',
        'rate',
        'effective_at',
        'created_by',
        'note',
    ];

    protected $casts = [
        // String, not float: a rate is money-adjacent and 18,8 does not survive
        // a round trip through a 64-bit float intact.
        'rate' => 'decimal:8',
        'effective_at' => 'datetime',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
