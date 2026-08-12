<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'product_unit_id',
        'unit_name',
        'unit_multiplier',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'unit_multiplier' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * Per-warehouse fulfilment plan. An item may be split across several
     * warehouses; the sum of allocation quantities equals `quantity`.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SalesOrderItemAllocation::class);
    }

    protected static function booted(): void
    {
        static::saving(function ($item) {
            $item->total = ($item->unit_price * $item->quantity) - $item->discount + $item->tax;
        });
    }
}
