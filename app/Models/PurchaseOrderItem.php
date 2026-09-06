<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'sale_price',
        'total_price',
        'notes',
    ];

    /*
     * The received_* columns stay out of $fillable: what a line cost to land
     * is settled by its receipts and the stock layers they opened, and is
     * never something a request may state.
     */
    protected $casts = [
        'quantity' => 'integer',
        'received_quantity' => 'integer',
        'received_unit_cost' => 'decimal:5',
        'received_cost' => 'decimal:5',
        'unit_price' => 'decimal:5',
        'sale_price' => 'decimal:5',
        'total_price' => 'decimal:5',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
