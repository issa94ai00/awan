<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'name_ar',
        'barcode',
        'base_unit_multiplier',
        'price_multiplier',
        'is_default',
    ];

    protected $casts = [
        'base_unit_multiplier' => 'decimal:2',
        'price_multiplier' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
