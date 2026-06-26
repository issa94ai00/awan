<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_date',
        'ledger_account_id',
        'description',
        'debit',
        'credit',
        'reference',
        'created_by',
        'currency',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function ledgerAccount()
    {
        return $this->belongsTo(LedgerAccount::class);
    }
}
