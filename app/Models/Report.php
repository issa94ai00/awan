<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'type',
        'format',
        'query_config',
        'filter_config',
        'column_config',
        'chart_config',
        'created_by',
        'is_public',
        'is_scheduled',
        'schedule_frequency',
        'schedule_config',
        'last_run_at',
    ];

    protected $casts = [
        'query_config' => 'array',
        'filter_config' => 'array',
        'column_config' => 'array',
        'chart_config' => 'array',
        'schedule_config' => 'array',
        'is_public' => 'boolean',
        'is_scheduled' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    const TYPE_SALES = 'sales';
    const TYPE_INVENTORY = 'inventory';
    const TYPE_WAREHOUSE = 'warehouse';
    const TYPE_FINANCIAL = 'financial';
    const TYPE_CUSTOMER = 'customer';
    const TYPE_CUSTOM = 'custom';

    const FORMAT_TABLE = 'table';
    const FORMAT_CHART = 'chart';
    const FORMAT_PIVOT = 'pivot';
    const FORMAT_SUMMARY = 'summary';

    const SCHEDULE_DAILY = 'daily';
    const SCHEDULE_WEEKLY = 'weekly';
    const SCHEDULE_MONTHLY = 'monthly';
    const SCHEDULE_QUARTERLY = 'quarterly';

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function widgets()
    {
        return $this->hasMany(DashboardWidget::class, 'report_id');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeScheduled($query)
    {
        return $query->where('is_scheduled', true);
    }

    public function getTypeTextAttribute(): string
    {
        return match($this->type) {
            self::TYPE_SALES => 'المبيعات',
            self::TYPE_INVENTORY => 'المخزون',
            self::TYPE_WAREHOUSE => 'المستودع',
            self::TYPE_FINANCIAL => 'المالي',
            self::TYPE_CUSTOMER => 'العملاء',
            self::TYPE_CUSTOM => 'مخصص',
            default => $this->type,
        };
    }

    public function getFormatTextAttribute(): string
    {
        return match($this->format) {
            self::FORMAT_TABLE => 'جدول',
            self::FORMAT_CHART => 'رسم بياني',
            self::FORMAT_PIVOT => 'محوري',
            self::FORMAT_SUMMARY => 'ملخص',
            default => $this->format,
        };
    }

    public function shouldRun(): bool
    {
        if (!$this->is_scheduled) {
            return false;
        }

        if (!$this->last_run_at) {
            return true;
        }

        return match($this->schedule_frequency) {
            self::SCHEDULE_DAILY => $this->last_run_at->lt(now()->subDay()),
            self::SCHEDULE_WEEKLY => $this->last_run_at->lt(now()->subWeek()),
            self::SCHEDULE_MONTHLY => $this->last_run_at->lt(now()->subMonth()),
            self::SCHEDULE_QUARTERLY => $this->last_run_at->lt(now()->subQuarter()),
            default => false,
        };
    }

    public function markAsRun(): void
    {
        $this->last_run_at = now();
        $this->save();
    }
}
