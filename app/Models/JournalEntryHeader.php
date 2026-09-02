<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntryHeader extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'reference_type',
        'reference_id',
        // Without these three here, mass assignment silently drops them —
        // posting_key in particular, which is what stops a document from being
        // posted to the ledger twice.
        'posting_key',
        'source_module',
        'reversal_of_id',
        'description',
        'total_debit',
        'total_credit',
        'currency',
        // What the amounts are actually in, and what they were converted at.
        // Stamped per entry: the base currency is configurable, and an entry
        // posted under the old one must not start claiming the new.
        'base_currency',
        'exchange_rate',
        'status',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:5',
        'total_credit' => 'decimal:5',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_header_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /** The entry this one cancels, when this is a reversing entry. */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }
}
