<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'metric_id',
        'report_id',
        'widget_type',
        'title',
        'title_ar',
        'config',
        'position_x',
        'position_y',
        'width',
        'height',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    const TYPE_NUMBER_CARD = 'number_card';
    const TYPE_CHART = 'chart';
    const TYPE_TABLE = 'table';
    const TYPE_GAUGE = 'gauge';
    const TYPE_PROGRESS = 'progress';
    const TYPE_LIST = 'list';

    public function dashboard()
    {
        return $this->belongsTo(Dashboard::class, 'dashboard_id');
    }

    public function metric()
    {
        return $this->belongsTo(AnalyticsMetric::class, 'metric_id');
    }

    public function report()
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDashboard($query, $dashboardId)
    {
        return $query->where('dashboard_id', $dashboardId);
    }

    public function getWidgetTypeTextAttribute(): string
    {
        return match($this->widget_type) {
            self::TYPE_NUMBER_CARD => 'بطاقة رقم',
            self::TYPE_CHART => 'رسم بياني',
            self::TYPE_TABLE => 'جدول',
            self::TYPE_GAUGE => 'مقياس',
            self::TYPE_PROGRESS => 'شريط تقدم',
            self::TYPE_LIST => 'قائمة',
            default => $this->widget_type,
        };
    }

    public function getDataAttribute()
    {
        if ($this->metric_id) {
            return [
                'value' => $this->metric->getLatestValueAttribute(),
                'trend' => $this->metric->getTrendAttribute(),
                'trend_percentage' => $this->metric->getTrendPercentageAttribute(),
                'unit' => $this->metric->unit,
            ];
        }

        return null;
    }
}
