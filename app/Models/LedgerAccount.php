<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'balance',
        'is_active',
        'account_type',
        'currency',
        'opening_balance',
        'is_system',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2',
        'is_system' => 'boolean',
    ];

    public function journalEntryLines()
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /**
     * Signed balance change for a posted line, based on the account's normal balance side.
     * Debit-normal (asset, expense): debit increases, credit decreases.
     * Credit-normal (liability, equity, revenue): credit increases, debit decreases.
     * Used identically to apply new lines and to reverse old ones (reversal = negate the result),
     * so the two paths can't drift apart.
     */
    public static function signedDelta(string $type, float $debit, float $credit): float
    {
        $debitNormal = in_array($type, ['asset', 'expense'], true);

        return $debitNormal ? ($debit - $credit) : ($credit - $debit);
    }
}
