<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'trigger_type',
        'trigger_config',
        'status',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'metadata' => 'array',
    ];

    const TRIGGER_MANUAL = 'manual';
    const TRIGGER_ORDER_CREATED = 'order_created';
    const TRIGGER_ORDER_STATUS_CHANGED = 'order_status_changed';
    const TRIGGER_INVENTORY_LOW = 'inventory_low';
    const TRIGGER_SCHEDULED = 'scheduled';
    const TRIGGER_WEBHOOK = 'webhook';

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_DRAFT = 'draft';

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('order');
    }

    public function executions()
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByTrigger($query, $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'نشط',
            self::STATUS_INACTIVE => 'غير نشط',
            self::STATUS_DRAFT => 'مسودة',
            default => $this->status,
        };
    }

    public function getTriggerTextAttribute(): string
    {
        return match($this->trigger_type) {
            self::TRIGGER_MANUAL => 'يدوي',
            self::TRIGGER_ORDER_CREATED => 'إنشاء طلب',
            self::TRIGGER_ORDER_STATUS_CHANGED => 'تغيير حالة طلب',
            self::TRIGGER_INVENTORY_LOW => 'انخفاض المخزون',
            self::TRIGGER_SCHEDULED => 'مجدول',
            self::TRIGGER_WEBHOOK => 'Webhook',
            default => $this->trigger_type,
        };
    }

    public function canExecute(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->steps()->count() > 0;
    }
}
