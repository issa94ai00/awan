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
        'requested_at',
        'shipped_at',
        'received_at',
        'notes',
        'created_by',
        'shipped_by',
        'received_by',
        'transfer_number',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(InventoryTransferItem::class);
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
                $transfer->transfer_number = 'TRF-' . str_pad(static::count() + 1, 6, '0', STR_PAD_LEFT);
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
            self::STATUS_PENDING => 'معلق',
            self::STATUS_IN_TRANSIT => 'قيد النقل',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    public function canShip(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canReceive(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function ship(): void
    {
        $this->status = self::STATUS_IN_TRANSIT;
        $this->shipped_at = now();
        $this->save();
    }

    public function receive(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->received_at = now();
        $this->save();
    }
}
