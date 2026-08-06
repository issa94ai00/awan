<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_id',
        'product_id',
        'product_variant_id',
        'quantity_requested',
        'quantity_shipped',
        'quantity_received',
        'batch_number',
        'expiry_date',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'unit_cost' => 'decimal:2',
    ];

    public function transfer()
    {
        return $this->belongsTo(InventoryTransfer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getTotalCostAttribute(): float
    {
        return $this->quantity_requested * $this->unit_cost;
    }

    public function getShippedCostAttribute(): float
    {
        return $this->quantity_shipped * $this->unit_cost;
    }

    public function getReceivedCostAttribute(): float
    {
        return $this->quantity_received * $this->unit_cost;
    }

    public function isFullyShipped(): bool
    {
        return $this->quantity_shipped >= $this->quantity_requested;
    }

    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_shipped;
    }
}
