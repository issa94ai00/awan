<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCommissionWithdrawal extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'employee_commission_id',
        'withdrawn_at',
        'currency_code',
        'amount',
        'exchange_rate',
        'base_amount',
        'method',
        'reason',
        'notes',
        'created_by',
        'deleted_by',
    ];

    protected $casts = [
        'withdrawn_at' => 'datetime',
        'amount' => 'decimal:5',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:5',
    ];

    const METHOD_CASH = 'cash';
    const METHOD_BANK = 'bank';

    public function employeeCommission(): BelongsTo
    {
        return $this->belongsTo(EmployeeCommission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
