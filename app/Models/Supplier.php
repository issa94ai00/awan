<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\GuardsDeletionWithHistory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory, GuardsDeletionWithHistory;

    protected static function deletionBlockers(): array
    {
        return [
        'purchase_orders' => ['supplier_id', 'أوامر شراء'],
        'purchase_receipts' => ['supplier_id', 'إيصالات استلام'],
        'supplier_payments' => ['supplier_id', 'دفعات موردين'],
        'purchase_returns' => ['supplier_id', 'مرتجعات شراء'],
        ];
    }

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
        'tax_number',
        'city',
        'state',
        'country',
        'postal_code',
        'currency',
        'lead_time_days',
        'total_purchases',
    ];

    protected $casts = [
        'balance' => 'decimal:5',
        'credit_limit' => 'decimal:5',
        'total_purchases' => 'decimal:5',
    ];

    public function orders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function purchaseReceipts()
    {
        return $this->hasMany(PurchaseReceipt::class);
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
        return $this->credit_limit - abs($this->balance);
    }
}
