<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'address',
        'status',
        'notes',
        'balance',
        'credit_limit',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
    ];

    public function orders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function purchaseReceipts()
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function updateBalance($amount): void
    {
        $this->increment('balance', $amount);
    }

    public function getRemainingCreditAttribute(): float
    {
        return $this->credit_limit - abs($this->balance);
    }
}
