<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Goods sent back to the supplier.
 *
 * The mirror of a credit note on the sales side. Without it the only way to
 * record a return was a stock adjustment, which books the goods out as
 * shrinkage — so returning a faulty delivery looked identical to losing it,
 * and the debt to the supplier stayed on the books in full.
 */
class PurchaseReturn extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'return_number',
        'supplier_id',
        'purchase_receipt_id',
        'warehouse_id',
        'return_date',
        'reason',
        'credit_amount',
        'tax_amount',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'credit_amount' => 'decimal:5',
        'tax_amount' => 'decimal:5',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The key its journal entry is posted under. */
    public function postingKey(): string
    {
        return 'purchase_return:' . $this->id;
    }

    /** What the returned units actually cost us, from the FIFO layers. */
    public function totalCost(): float
    {
        return round((float) $this->items->sum(fn ($item) => (float) $item->unit_cost * (int) $item->quantity), 2);
    }
}
