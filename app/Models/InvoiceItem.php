<?php

namespace App\Models;

use App\Models\Concerns\SoldInUnits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory, SoldInUnits;

    protected $fillable = [
        'invoice_id',
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'quantity',
        'unit_price',
        'total_price',
        'notes',
        'discount',
        'tax_rate',
        'tax_amount',
        'product_unit_id',
        'unit_name',
        'unit_multiplier',
    ];

    /*
     * unit_cost and total_cost are deliberately absent from $fillable: what a
     * line cost is measured by the goods issue, out of the stock layers it
     * consumed, and is never something a request may state.
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:5',
        'total_price' => 'decimal:5',
        'unit_cost' => 'decimal:5',
        'total_cost' => 'decimal:5',
        'discount' => 'decimal:5',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:5',
        'unit_multiplier' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * What the customer actually paid for one unit of this line.
     *
     * The line's own discount and tax (a line raised from a sales order
     * carries both), then the invoice's header discount and tax spread over
     * its goods by value — the subtotal is the sum of the lines' values, and
     * the header discount and tax were charged on it. Delivery and other
     * additional charges are not goods and are not part of it.
     *
     * A return is refunded at this, not at the list unit price: refunding the
     * list price handed back the discount the customer never paid, and the
     * credit note then reversed more revenue than the sale had booked.
     */
    public function refundableUnitPrice(): float
    {
        $quantity = (int) $this->quantity;
        if ($quantity <= 0) {
            return 0.0;
        }

        $lineValue = $quantity * (float) $this->unit_price
            - (float) ($this->discount ?? 0)
            + (float) ($this->tax_amount ?? 0);

        return round(max(0, $lineValue) / $quantity * ($this->invoice?->paidShareOfSubtotal() ?? 1.0), 5);
    }

    protected static function booted(): void
    {
        static::saving(function ($item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
