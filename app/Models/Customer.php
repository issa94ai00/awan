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
        'tax_number',
        'city',
        'state',
        'country',
        'postal_code',
        'currency',
        'total_purchases',
        'last_purchase_at',
        'password',
        'auth_token',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'last_purchase_at' => 'date',
    ];

    protected $hidden = [
        'password',
        'auth_token',
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

    public function contacts()
    {
        return $this->morphMany(Contact::class, 'contactable');
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
