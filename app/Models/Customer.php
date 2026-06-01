<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'address',
        'source',
        'status',
        'notes',
        'balance',
        'credit_limit',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function updateBalance($amount): void
    {
        $this->increment('balance', $amount);
    }

    public function getRemainingCreditAttribute(): float
    {
        return $this->credit_limit - $this->balance;
    }
}
