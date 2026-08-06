<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::saved(function ($item) {
            // Automatically update stock when purchase receipt item is saved
            if ($item->product) {
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'movement_type' => 'in',
                    'quantity' => $item->quantity,
                    'reference' => $item->purchaseReceipt->receipt_number,
                    'source' => 'purchase_receipt',
                    'notes' => 'استلام من أمر شراء',
                ]);
                
                $item->product->increment('stock_quantity', $item->quantity);
            }
        });
    }
}
