<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackingListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'packing_list_id',
        'picking_list_item_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'package_number',
        'dimensions',
        'weight',
        'fragile',
        'notes',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'weight' => 'decimal:2',
        'fragile' => 'boolean',
    ];

    public function packingList()
    {
        return $this->belongsTo(PackingList::class);
    }

    public function pickingListItem()
    {
        return $this->belongsTo(PickingListItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getVolumeAttribute(): float
    {
        if (!$this->dimensions) {
            return 0;
        }
        return ($this->dimensions['length'] ?? 0) * 
               ($this->dimensions['width'] ?? 0) * 
               ($this->dimensions['height'] ?? 0);
    }
}
