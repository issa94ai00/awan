<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'fulfillment_method',
        'requested_at',
        'approved_at',
        'shipped_at',
        'received_at',
        'notes',
        'created_by',
        'approved_by',
        'shipped_by',
        'received_by',
        'transfer_number',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_IN_TRANSIT = 'in_transit';
    /** Approved for collection: the goods are set aside at the source. */
    const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /** Someone carries the goods to the branch. */
    const METHOD_DELIVERY = 'delivery';
    /** The requester collects them from the source warehouse in person. */
    const METHOD_PICKUP = 'pickup';

    /** Stages at which the source is holding stock it has not yet released. */
    const HELD_STAGES = [self::STATUS_PENDING, self::STATUS_READY_FOR_PICKUP];

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * The foreign key is `transfer_id`, not the `inventory_transfer_id` Laravel
     * infers from the class name. Without naming it, the relation queried a
     * column that does not exist and threw on every access — which is every
     * ship, receive and cancel in InventoryTransferController.
     */
    public function items()
    {
        return $this->hasMany(InventoryTransferItem::class, 'transfer_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shippedBy()
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (empty($transfer->transfer_number)) {
                // Derived from the last id, not a count: counting reuses a
                // number the moment any transfer is deleted, and the column is
                // unique — so the next insert failed rather than renumbering.
                $transfer->transfer_number = 'TRF-' . str_pad(
                    (string) (((int) static::max('id')) + 1), 6, '0', STR_PAD_LEFT
                );
            }
        });
    }

    public function getTotalQuantityAttribute(): int
    {
        return $this->items->sum('quantity_requested');
    }

    public function getShippedQuantityAttribute(): int
    {
        return $this->items->sum('quantity_shipped');
    }

    public function getReceivedQuantityAttribute(): int
    {
        return $this->items->sum('quantity_received');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', self::STATUS_IN_TRANSIT);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'بانتظار موافقة المستودع',
            self::STATUS_IN_TRANSIT => 'قيد النقل إلى الفرع',
            self::STATUS_READY_FOR_PICKUP => 'جاهز للاستلام من المستودع',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    public function getFulfillmentMethodTextAttribute(): ?string
    {
        return match($this->fulfillment_method) {
            self::METHOD_DELIVERY => 'شحن إلى الفرع',
            self::METHOD_PICKUP => 'استلام من المستودع',
            default => null,
        };
    }

    /** Waiting on the source warehouse to decide how it will be fulfilled. */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Ready for the requester to take delivery.
     *
     * Both approved stages qualify, and they mean different things physically:
     * in transit the goods have already left the source, waiting for pickup
     * they are still on its shelf. What happens at receipt differs accordingly
     * — see ReplenishmentWorkflowService::receive.
     */
    public function canReceive(): bool
    {
        return in_array($this->status, [self::STATUS_IN_TRANSIT, self::STATUS_READY_FOR_PICKUP], true);
    }

    /**
     * Cancellable until the goods physically leave.
     *
     * Stock set aside for collection can still be released — nothing has moved.
     * Once it is in transit it is somewhere on a road, and calling that off is
     * a return, not a cancellation.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, self::HELD_STAGES, true);
    }

    /** Whether the source still holds a reservation for this transfer. */
    public function holdsReservation(): bool
    {
        return in_array($this->status, self::HELD_STAGES, true);
    }

    public function isPickup(): bool
    {
        return $this->fulfillment_method === self::METHOD_PICKUP;
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeReadyForPickup($query)
    {
        return $query->where('status', self::STATUS_READY_FOR_PICKUP);
    }
}
