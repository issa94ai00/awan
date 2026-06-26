<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'bin_id',
        'count_number',
        'type',
        'status',
        'counter_id',
        'reviewer_id',
        'started_at',
        'completed_at',
        'reviewed_at',
        'total_items',
        'variance_items',
        'variance_value',
        'requires_adjustment',
        'adjustment_by',
        'adjusted_at',
        'notes',
    ];

    protected $casts = [
        'variance_value' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'adjusted_at' => 'datetime',
        'requires_adjustment' => 'boolean',
    ];

    const TYPE_FULL = 'full';
    const TYPE_PARTIAL = 'partial';
    const TYPE_ABC = 'abc';
    const TYPE_BLIND = 'blind';

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bin()
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    public function counter()
    {
        return $this->belongsTo(User::class, 'counter_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function adjuster()
    {
        return $this->belongsTo(User::class, 'adjustment_by');
    }

    public function items()
    {
        return $this->hasMany(CycleCountItem::class);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByBin($query, $binId)
    {
        return $query->where('bin_id', $binId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRequiresAdjustment($query)
    {
        return $query->where('requires_adjustment', true);
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

    public function getTypeTextAttribute(): string
    {
        return match($this->type) {
            self::TYPE_FULL => 'كامل',
            self::TYPE_PARTIAL => 'جزئي',
            self::TYPE_ABC => 'ABC',
            self::TYPE_BLIND => 'أعمى',
            default => $this->type,
        };
    }

    public function canStart(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function start($counterId): void
    {
        $this->status = self::STATUS_IN_PROGRESS;
        $this->counter_id = $counterId;
        $this->started_at = now();
        $this->save();
    }

    public function complete(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    public function review($reviewerId): void
    {
        $this->reviewer_id = $reviewerId;
        $this->reviewed_at = now();
        $this->save();
    }

    public function markRequiresAdjustment(): void
    {
        $this->requires_adjustment = true;
        $this->save();
    }

    public function applyAdjustment($userId): void
    {
        $this->requires_adjustment = false;
        $this->adjustment_by = $userId;
        $this->adjusted_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    public function generateCountNumber(): string
    {
        return 'CC-' . str_pad($this->id ?? CycleCount::count() + 1, 6, '0', STR_PAD_LEFT);
    }

    public function calculateVariance(): void
    {
        $this->variance_items = $this->items()->where('variance', '!=', 0)->count();
        $this->variance_value = $this->items()->sum('variance_value');
        $this->requires_adjustment = $this->variance_items > 0;
        $this->save();
    }
}
