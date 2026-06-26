<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_number',
        'customer_id',
        'status',
        'valid_until',
        'subtotal',
        'tax',
        'discount',
        'total',
        'notes',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'valid_until' => 'date',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function salesOrder()
    {
        return $this->hasOne(SalesOrder::class, 'quote_id');
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'مسودة',
            self::STATUS_SENT => 'مرسلة',
            self::STATUS_ACCEPTED => 'مقبولة',
            self::STATUS_REJECTED => 'مرفوضة',
            self::STATUS_EXPIRED => 'منتهية الصلاحية',
            default => $this->status,
        };
    }

    public function generateQuoteNumber(): string
    {
        return 'QT-' . str_pad($this->id ?? Quote::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
