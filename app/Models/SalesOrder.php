<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'quote_id',
        'status',
        'order_date',
        'expected_delivery',
        'subtotal',
        'tax',
        'discount',
        'total',
        'shipping_address',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'order_date' => 'date',
        'expected_delivery' => 'date',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'sales_order_id');
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_CONFIRMED => 'مؤكد',
            self::STATUS_PROCESSING => 'قيد المعالجة',
            self::STATUS_SHIPPED => 'تم الشحن',
            self::STATUS_DELIVERED => 'تم التسليم',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    public function generateOrderNumber(): string
    {
        return 'SO-' . str_pad($this->id ?? SalesOrder::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
