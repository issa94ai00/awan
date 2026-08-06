<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A credit note: an amount the business owes a customer, usually because goods
 * came back on a return.
 *
 * The note is settled by consuming it in any combination of three ways, each
 * tracked separately so the settlement stays auditable:
 *
 *   applied_to_invoice  — offset against the invoice's outstanding balance
 *   refunded_amount     — paid back in cash / bank transfer / cheque
 *   store_credit_amount — left on the customer's account
 */
class CreditNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'credit_note_number',
        'customer_id',
        'invoice_id',
        'rma_request_id',
        'issue_date',
        'subtotal',
        'tax',
        'total',
        'applied_to_invoice',
        'refunded_amount',
        'store_credit_amount',
        'status',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'applied_to_invoice' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'store_credit_amount' => 'decimal:2',
    ];

    protected $appends = ['settled_amount', 'open_amount', 'status_text'];

    const STATUS_ISSUED = 'issued';
    const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    const STATUS_APPLIED = 'applied';
    const STATUS_CANCELLED = 'cancelled';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function rmaRequest()
    {
        return $this->belongsTo(RmaRequest::class);
    }

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Everything consumed so far, however it was consumed. */
    public function getSettledAmountAttribute(): float
    {
        return round(
            (float) $this->applied_to_invoice
            + (float) $this->refunded_amount
            + (float) $this->store_credit_amount,
            2
        );
    }

    /** What is still owed to the customer under this note. */
    public function getOpenAmountAttribute(): float
    {
        return round(max(0, (float) $this->total - $this->settled_amount), 2);
    }

    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ISSUED => 'صادر',
            self::STATUS_PARTIALLY_APPLIED => 'مُطبَّق جزئياً',
            self::STATUS_APPLIED => 'مُطبَّق بالكامل',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    /**
     * Recomputes the status from what has actually been consumed, so it can
     * never drift away from the amounts.
     */
    public function syncStatus(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        $settled = $this->settled_amount;

        // Tolerance keeps rounding noise from leaving a note perpetually "partial".
        $this->status = match (true) {
            $settled <= 0.009 => self::STATUS_ISSUED,
            $settled >= (float) $this->total - 0.009 => self::STATUS_APPLIED,
            default => self::STATUS_PARTIALLY_APPLIED,
        };

        $this->save();
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_ISSUED, self::STATUS_PARTIALLY_APPLIED]);
    }

    public static function nextNumber(): string
    {
        return 'CN-' . str_pad((string) (self::withTrashed()->max('id') + 1), 6, '0', STR_PAD_LEFT);
    }
}
