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
        'subtotal' => 'decimal:5',
        'tax' => 'decimal:5',
        'discount' => 'decimal:5',
        'total' => 'decimal:5',
        'valid_until' => 'date',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    /**
     * Where a quote may go from each status. Revising a rejected or lapsed
     * quote goes back through draft, so it is resent rather than revived as
     * it stood; an acceptance can be undone only while no order came of it.
     */
    const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SENT, self::STATUS_ACCEPTED, self::STATUS_REJECTED],
        self::STATUS_SENT => [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EXPIRED, self::STATUS_DRAFT],
        self::STATUS_ACCEPTED => [self::STATUS_SENT],
        self::STATUS_REJECTED => [self::STATUS_DRAFT],
        self::STATUS_EXPIRED => [self::STATUS_DRAFT],
    ];

    /** Only a quote still being negotiated has lines worth rewriting. */
    const EDITABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_SENT];

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

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** A quote whose validity ran out while it was still open. */
    public function isPastValidity(): bool
    {
        return $this->valid_until !== null
            && $this->valid_until->lt(today())
            && in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    /**
     * Derived from the last id, not the row count: counting reuses a number
     * the moment any quote is deleted, and the unique index then refuses the
     * next quote outright.
     */
    public static function nextNumber(): string
    {
        $next = ((int) static::max('id')) + 1;

        do {
            $number = 'QT-'.str_pad((string) $next++, 6, '0', STR_PAD_LEFT);
        } while (static::where('quote_number', $number)->exists());

        return $number;
    }
}
