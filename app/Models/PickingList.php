<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'sales_order_id',
        'list_number',
        'priority',
        'status',
        'picker_id',
        'started_at',
        'completed_at',
        'total_items',
        'picked_items',
        'route',
        'notes',
    ];

    protected $casts = [
        'route' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function picker()
    {
        return $this->belongsTo(User::class, 'picker_id');
    }

    public function items()
    {
        return $this->hasMany(PickingListItem::class);
    }

    public function packingLists()
    {
        return $this->hasMany(PackingList::class);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPicker($query, $pickerId)
    {
        return $query->where('picker_id', $pickerId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
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

    public function getPriorityTextAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'منخفض',
            self::PRIORITY_NORMAL => 'عادي',
            self::PRIORITY_HIGH => 'عالي',
            self::PRIORITY_URGENT => 'عاجل',
            default => $this->priority,
        };
    }

    public function getCompletionPercentageAttribute(): float
    {
        if ($this->total_items <= 0) {
            return 0;
        }
        return min(100, ($this->picked_items / $this->total_items) * 100);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canStart(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function start($pickerId): void
    {
        $this->status = self::STATUS_IN_PROGRESS;
        $this->picker_id = $pickerId;
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
        return 'PL-' . str_pad($this->id ?? PickingList::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
