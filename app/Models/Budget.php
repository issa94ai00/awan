<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a year was meant to earn and spend.
 *
 * Gives every other figure in the books a verdict: 40,000 of expense in a
 * quarter is a fact, and only a figure set in advance says whether it was
 * under control or an overrun.
 */
class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'fiscal_year',
        'status',
        'notes',
        'created_by',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The whole year's figure for one account. */
    public function annualFor(int $accountId): float
    {
        return round((float) $this->lines()->where('account_id', $accountId)->sum('amount'), 2);
    }
}
