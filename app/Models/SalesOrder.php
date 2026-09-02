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
        'currency',
        'paid_amount',
        'due_amount',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'channel_id',
        'external_order_id',
        'contract_id',
        'fulfillment_type',
        'fulfillment_warehouse_id',
        'actual_delivery_date',
        'billing_address',
        'tracking_number',
        'carrier',
        'shipping_cost',
        'coupon_code',
        'customer_notes',
        'internal_notes',
        'synced_at',
        'sync_status',
        'assigned_employee_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:5',
        'tax' => 'decimal:5',
        'discount' => 'decimal:5',
        'total' => 'decimal:5',
        'order_date' => 'date',
        'expected_delivery' => 'date',
        'paid_amount' => 'decimal:5',
        'due_amount' => 'decimal:5',
        'confirmed_at' => 'date',
        'shipped_at' => 'date',
        'delivered_at' => 'date',
        'actual_delivery_date' => 'datetime',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'shipping_cost' => 'decimal:5',
        'synced_at' => 'datetime',
    ];

    const FULFILLMENT_SHIP = 'ship';

    const FULFILLMENT_PICKUP = 'pickup';

    const FULFILLMENT_DELIVERY = 'delivery';

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

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'sales_order_id');
    }

    public function channel()
    {
        return $this->belongsTo(OrderChannel::class, 'channel_id');
    }

    public function contract()
    {
        return $this->belongsTo(SalesContract::class, 'contract_id');
    }

    public function fulfillmentWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'fulfillment_warehouse_id');
    }

    /**
     * The warehouses this order is deliberately routed through.
     *
     * `fulfillment_warehouse_id` above names the one that owns the order — whose
     * branch list it appears in, and where an unallocated line is picked from.
     * This names every warehouse allowed to supply it, which for a split order
     * is more than one and always includes the owner.
     *
     * Empty means nobody has narrowed it down, and every active warehouse is
     * fair game — the behaviour before this existed.
     */
    public function routings()
    {
        return $this->belongsToMany(Warehouse::class, 'sales_order_routings')
            ->withTimestamps()
            ->orderBy('warehouses.id');
    }

    public function rmaRequests()
    {
        return $this->hasMany(RmaRequest::class);
    }

    /** Append-only record of every stage this order has moved through. */
    public function statusHistory()
    {
        return $this->hasMany(SalesOrderStatusHistory::class)->orderBy('id');
    }

    public function getStatusTextAttribute()
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_CONFIRMED => 'مؤكد',
            self::STATUS_PROCESSING => 'قيد المعالجة',
            self::STATUS_SHIPPED => 'تم الشحن',
            self::STATUS_DELIVERED => 'تم التسليم',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    public function getFulfillmentTypeTextAttribute(): string
    {
        return match ($this->fulfillment_type) {
            self::FULFILLMENT_SHIP => 'شحن',
            self::FULFILLMENT_PICKUP => 'استلام من الفرع',
            self::FULFILLMENT_DELIVERY => 'توصيل',
            default => $this->fulfillment_type,
        };
    }

    public function generateOrderNumber(): string
    {
        return 'SO-'.str_pad($this->id ?? SalesOrder::count() + 1, 6, '0', STR_PAD_LEFT);
    }

    public function scopeByChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByContract($query, $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    protected static function booted()
    {
        static::saving(function (SalesOrder $order) {
            // The seller who owns the order works out of one building, and that
            // is a real answer to "where does this come from".
            //
            // What used to follow it was not: an order with no employee fell
            // back to `Warehouse::active()->orderBy('id')` — the lowest-numbered
            // active warehouse, which is the main one. Nothing about the goods
            // was consulted, and because the field was then never empty,
            // confirmation's stock-aware routing never ran. Every web and
            // customer order was pinned to the main warehouse whether or not it
            // held any of the stock.
            //
            // Left null instead. SalesOrderWorkflowService::planSourcing settles
            // it at confirmation, against the figures.
            if (! $order->fulfillment_warehouse_id && $order->assigned_employee_id) {
                $order->fulfillment_warehouse_id = Employee::find($order->assigned_employee_id)?->warehouse_id;
            }
        });
    }

    public function scopePendingSync($query)
    {
        return $query->whereNull('synced_at')->orWhere('sync_status', 'pending');
    }

    public function isMultiChannel(): bool
    {
        return ! is_null($this->channel_id);
    }

    public function markAsSynced(): void
    {
        $this->synced_at = now();
        $this->sync_status = 'synced';
        $this->save();
    }
}
