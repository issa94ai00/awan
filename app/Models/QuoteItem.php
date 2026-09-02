<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:5',
        'discount' => 'decimal:5',
        'tax' => 'decimal:5',
        'total' => 'decimal:5',
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::saving(function ($item) {
            $item->total = ($item->unit_price * $item->quantity) - $item->discount + $item->tax;
        });
    }
}
