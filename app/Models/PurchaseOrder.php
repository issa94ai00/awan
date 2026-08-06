<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'order_number',
        'status',
        'total',
        'tax',
        'discount',
        'due_date',
        'notes',
        'created_by',
        'subtotal',
        'currency',
        'order_date',
        'received_date',
        'paid_amount',
        'due_amount',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'order_date' => 'date',
        'received_date' => 'date',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
