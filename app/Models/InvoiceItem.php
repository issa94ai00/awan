<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'warehouse_id',
        'product_id',
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

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:5',
        'total_price' => 'decimal:5',
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

    protected static function booted(): void
    {
        static::saving(function ($item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });
    }
}
