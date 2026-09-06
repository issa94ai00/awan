<?php

namespace App\Models;

use App\Models\Concerns\RecordsInBaseCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;
    use RecordsInBaseCurrency;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** The statuses the workflow actually uses, in the order it walks them. */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Spellings written by earlier versions of this screen, mapped onto the
     * stage they meant. Rows carrying them still exist, and a list that could
     * not place them showed orders with no available action at all.
     */
    public const LEGACY_STATUSES = [
        'draft' => self::STATUS_PENDING,
        'ordered' => self::STATUS_CONFIRMED,
        'received' => self::STATUS_COMPLETED,
        'complete' => self::STATUS_COMPLETED,
        'canceled' => self::STATUS_CANCELLED,
    ];

    /**
     * Which stages an order may move to from where it stands.
     *
     * Completed and cancelled are terminal on purpose: completion is written by
     * a goods receipt that moved stock and posted a journal entry, and reopening
     * it here would leave those behind; a cancelled order should never come back
     * to life under the same number.
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'supplier_id',
        'order_number',
        'status',
        'total',
        'tax',
        'discount',
        'due_date',
        'notes',
        'created_by',
        'subtotal',
        'currency',
        'order_date',
        'received_date',
        'paid_amount',
        'due_amount',
    ];

    protected $casts = [
        'total' => 'decimal:5',
        'tax' => 'decimal:5',
        'discount' => 'decimal:5',
        'due_date' => 'date',
        'subtotal' => 'decimal:5',
        'order_date' => 'date',
        'received_date' => 'date',
        'paid_amount' => 'decimal:5',
        'due_amount' => 'decimal:5',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts()
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    /** The stage this order is at, whatever spelling its status was stored in. */
    public static function normalizeStatus(?string $status): string
    {
        $value = strtolower(trim((string) $status));

        return self::LEGACY_STATUSES[$value] ?? $value;
    }

    /** Goods can only be received against an order that was approved first. */
    public function isReceivable(): bool
    {
        return in_array(
            self::normalizeStatus($this->status),
            [self::STATUS_CONFIRMED, self::STATUS_PROCESSING],
            true
        );
    }
}
