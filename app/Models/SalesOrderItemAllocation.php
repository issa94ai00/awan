<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A planned warehouse share of a sales-order item.
 *
 * The order item carries the total quantity; allocations break that total
 * into per-warehouse quantities (the sum of `quantity` across allocations
 * equals the item quantity). Created when staff confirm the suggested split
 * or adjust it manually.
 */
class SalesOrderItemAllocation extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_ALLOCATED = 'allocated';
    const STATUS_PICKED = 'picked';
    const STATUS_FULFILLED = 'fulfilled';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'sales_order_item_id',
        'warehouse_id',
        'quantity',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
