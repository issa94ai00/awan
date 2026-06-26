<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'picking_list_id',
        'sales_order_id',
        'list_number',
        'status',
        'packer_id',
        'started_at',
        'completed_at',
        'total_packages',
        'total_weight',
        'dimensions',
        'box_type',
        'packing_instructions',
        'notes',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_weight' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function pickingList()
    {
        return $this->belongsTo(PickingList::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function packer()
    {
        return $this->belongsTo(User::class, 'packer_id');
    }

    public function items()
    {
        return $this->hasMany(PackingListItem::class);
    }

    public function shippingManifestItems()
    {
        return $this->hasMany(ShippingManifestItem::class);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPacker($query, $packerId)
    {
        return $query->where('packer_id', $packerId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_IN_PROGRESS => 'قيد التنفيذ',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canStart(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->pickingList?->isCompleted();
    }

    public function start($packerId): void
    {
        $this->status = self::STATUS_IN_PROGRESS;
        $this->packer_id = $packerId;
        $this->started_at = now();
        $this->save();
    }

    public function complete(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    public function generateListNumber(): string
    {
        return 'PCK-' . str_pad($this->id ?? PackingList::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
