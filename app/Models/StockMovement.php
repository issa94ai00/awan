<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'movement_type',
        'quantity',
        'reference',
        'source',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';
    const TYPE_ADJUSTMENT = 'adjustment';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::created(function ($movement) {
            // Automatically update product stock quantity
            if ($movement->product) {
                if ($movement->movement_type === self::TYPE_IN) {
                    $movement->product->increment('stock_quantity', $movement->quantity);
                } elseif ($movement->movement_type === self::TYPE_OUT) {
                    $movement->product->decrement('stock_quantity', $movement->quantity);
                } elseif ($movement->movement_type === self::TYPE_ADJUSTMENT) {
                    $movement->product->increment('stock_quantity', $movement->quantity);
                }
            }
        });
    }

    public function getMovementTypeTextAttribute()
    {
        return match($this->movement_type) {
            self::TYPE_IN => 'داخل',
            self::TYPE_OUT => 'خارج',
            self::TYPE_ADJUSTMENT => 'تعديل',
            default => $this->movement_type,
        };
    }
}
