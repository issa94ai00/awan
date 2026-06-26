<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'contract_number',
        'start_date',
        'end_date',
        'status',
        'total_value',
        'discount_percentage',
        'terms',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_value' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'terms' => 'array',
        'approved_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'contract_id');
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'مسودة',
            self::STATUS_ACTIVE => 'نشط',
            self::STATUS_EXPIRED => 'منتهي',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    public function isExpired(): bool
    {
        return $this->end_date < now() || $this->status === self::STATUS_EXPIRED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->isExpired();
    }

    public function approve($userId): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    public function getRemainingValueAttribute(): float
    {
        $orderTotal = $this->salesOrders()->sum('total');
        return max(0, $this->total_value - $orderTotal);
    }

    public function generateContractNumber(): string
    {
        return 'CTR-' . str_pad($this->id ?? SalesContract::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
