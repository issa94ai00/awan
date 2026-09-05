<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceOfferListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_offer_list_id',
        'product_id',
        'product_variant_id',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(PriceOfferList::class, 'price_offer_list_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}