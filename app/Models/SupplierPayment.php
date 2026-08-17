<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money paid out to a supplier — the settlement side of a purchase.
 *
 * Mirrors `Payment` on the customer side, deliberately as a separate document:
 * see the table migration for why the two are not one table.
 */
class SupplierPayment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'payment_number',
        'supplier_id',
        'purchase_receipt_id',
        'purchase_order_id',
        'payment_method',
        'amount',
        'payment_date',
        'reference',
        'notes',
        'currency',
        'status',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /** Cash leaves the till; everything else moves through the bank. */
    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CHECK = 'check';

    public const METHOD_CARD = 'card';

    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_CHECK,
        self::METHOD_CARD,
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The key its journal entry is posted under. */
    public function postingKey(): string
    {
        return 'supplier_payment:'.$this->id;
    }

    public function getPaymentMethodTextAttribute(): string
    {
        return match ($this->payment_method) {
            self::METHOD_CASH => 'نقدي',
            self::METHOD_CARD => 'بطاقة',
            self::METHOD_BANK_TRANSFER => 'تحويل بنكي',
            self::METHOD_CHECK => 'شيك',
            default => (string) $this->payment_method,
        };
    }
}
