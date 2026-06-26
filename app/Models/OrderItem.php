<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_title',
        'product_image',
        'product_brand',
        'price',
        'price_after_discount',
        'quantity',
        'size',
        'color',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_after_discount' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalAttribute()
    {
        $price = $this->price_after_discount ?? $this->price;
        return $price * $this->quantity;
    }
}
