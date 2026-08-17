<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_header_id',
        'account_id',
        'debit',
        'credit',
        'description',
        'employee_id',
        // Which part of the business the figure belongs to. Nullable on
        // purpose: a shared cost genuinely belongs to no branch, and forcing an
        // attribution invents precision the reports would then present as fact.
        'cost_center_id',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(JournalEntryHeader::class, 'journal_entry_header_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }
}
