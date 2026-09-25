<?php

namespace App\Models;

use App\Models\Concerns\RecordsInBaseCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    use HasFactory;
    use RecordsInBaseCurrency;

    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'supplier_id',
        // Without this the controller's validated warehouse_id was silently
        // discarded on create, so the receipt never recorded where the goods
        // it brought in actually went.
        'warehouse_id',
        'receipt_date',
        // Tax the supplier charged, held apart from the goods: it is a claim
        // against the tax authority, not part of what the stock cost.
        'tax_amount',
        'status',
        'currency',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'tax_amount' => 'decimal:5',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    /** What has been paid against this receipt (cancelled payments are soft-deleted). */
    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** What the supplier is owed for it: the goods at their price, plus the tax. */
    public function totalAmount(): float
    {
        return round(
            $this->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price)
            + (float) ($this->tax_amount ?? 0),
            2
        );
    }

    public function paidAmount(): float
    {
        return round((float) $this->payments->sum('amount'), 2);
    }

    public function dueAmount(): float
    {
        return max(0, round($this->totalAmount() - $this->paidAmount(), 2));
    }

    /**
     * The receipt as the screen reads it: with what it cost, what was paid
     * and how. Kept out of $appends and the attributes — a receipt serialised
     * inside a payment would load its lines and payments one query at a time,
     * and a save would try to write columns that do not exist.
     *
     * @return array<string, mixed>
     */
    public function toArrayWithPayments(): array
    {
        $this->loadMissing('items', 'payments');

        return array_merge($this->toArray(), [
            'total_amount' => $this->totalAmount(),
            'paid_amount' => $this->paidAmount(),
            'due_amount' => $this->dueAmount(),
            'payment_methods' => $this->payments->pluck('payment_method')->unique()->values()->all(),
        ]);
    }

    public function generateReceiptNumber(): string
    {
        return 'PR-' . str_pad($this->id ?? PurchaseReceipt::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
