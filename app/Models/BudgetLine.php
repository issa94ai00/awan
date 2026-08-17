<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One account's figure for one month.
 *
 * Monthly rather than annual because spending is rarely even — rent is the same
 * every month and marketing is not — and a yearly figure divided by twelve
 * produces variances that are really just seasonality.
 */
class BudgetLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'account_id',
        'month',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'month' => 'integer',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }
}
