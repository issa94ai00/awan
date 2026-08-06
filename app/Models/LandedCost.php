<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandedCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_id',
        'shipping_charges',
        'customs_duties',
        'insurance_cost',
        'other_charges',
        'allocation_method',
    ];

    protected $casts = [
        'shipping_charges' => 'decimal:2',
        'customs_duties' => 'decimal:2',
        'insurance_cost' => 'decimal:2',
        'other_charges' => 'decimal:2',
    ];

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }
}
