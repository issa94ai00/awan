<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'sales_order_id',
        'subtotal',
        'tax',
        'discount',
        'additional_charges',
        'total',
        'paid_amount',
        'due_amount',
        'payment_method',
        'status',
        'notes',
        'created_by',
        'paid_at',
        'currency',
        'exchange_rate',
        'due_date',
        'reference',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'exchange_rate' => 'decimal:4',
        'due_date' => 'date',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_CASH = 'cash';
    const PAYMENT_CARD = 'card';
    const PAYMENT_TRANSFER = 'transfer';

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'معلق',
            self::STATUS_CONFIRMED => 'مؤكد',
            self::STATUS_PROCESSING => 'قيد المعالجة',
            self::STATUS_SHIPPED => 'تم الشحن',
            self::STATUS_DELIVERED => 'تم التسليم',
            self::STATUS_CANCELLED => 'ملغي',
        ];
    }

    public static function getPaymentOptions(): array
    {
        return [
            self::PAYMENT_CASH => 'نقدي',
            self::PAYMENT_CARD => 'بطاقة',
            self::PAYMENT_TRANSFER => 'تحويل',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::getPaymentOptions()[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Whether the invoice has actually been collected.
     *
     * This used to return `status === 'delivered'`, which answers a different
     * question entirely: delivery is about where the goods are, payment about
     * whether the money arrived. A delivered-but-unpaid invoice reported itself
     * as settled, and an invoice paid in full while still in the warehouse
     * reported itself as outstanding. Settlement is decided by the amounts.
     */
    public function isPaid(): bool
    {
        return $this->amountDue() <= 0.009;
    }

    /** What is still owed on this invoice. */
    public function amountDue(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isShipped(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Records that the invoice has been settled.
     *
     * It used to set the status to `delivered` — describing the goods as having
     * arrived because the money had, which then fed the wrong figure into every
     * screen reading the status. Only the payment fields are touched; where the
     * goods are is the order's business.
     */
    public function markAsPaid(): void
    {
        $this->update([
            'paid_amount' => $this->total,
            'due_amount' => 0,
            'paid_at' => now(),
        ]);
    }

    public function markAsConfirmed(): void
    {
        $this->update([
            'status' => self::STATUS_CONFIRMED,
        ]);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
        ]);
    }

    public function markAsShipped(): void
    {
        $this->update([
            'status' => self::STATUS_SHIPPED,
        ]);
    }

    /**
     * Marks the goods delivered. Deliberately does not stamp `paid_at`: it
     * previously did, so delivering an invoice recorded a payment that had
     * never been received.
     */
    public function markAsDelivered(): void
    {
        $this->update(['status' => self::STATUS_DELIVERED]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'paid_at' => null,
        ]);
    }

    // Revenue calculation based on status
    public function isRevenueRecognized(): bool
    {
        return in_array($this->status, [
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED
        ]);
    }

    public function getRecognizedRevenue(): float
    {
        if ($this->isCancelled()) {
            return 0;
        }
        
        if ($this->isRevenueRecognized()) {
            return (float) $this->total;
        }
        
        return 0;
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** Credits raised against this invoice, typically from returns. */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return "{$prefix}-{$date}-{$random}";
    }
}
