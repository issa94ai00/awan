<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'rma_item_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Keeps the line total consistent with its parts, matching how
        // SalesOrderItem behaves.
        static::saving(function ($item) {
            $item->total = round((float) $item->unit_price * (int) $item->quantity, 2);
        });
    }

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function rmaItem()
    {
        return $this->belongsTo(RmaItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
