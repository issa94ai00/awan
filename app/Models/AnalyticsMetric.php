<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_key',
        'name',
        'name_ar',
        'description',
        'category',
        'data_type',
        'aggregation',
        'unit',
        'calculation_config',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'calculation_config' => 'array',
        'is_active' => 'boolean',
    ];

    const CATEGORY_SALES = 'sales';
    const CATEGORY_INVENTORY = 'inventory';
    const CATEGORY_WAREHOUSE = 'warehouse';
    const CATEGORY_FINANCIAL = 'financial';
    const CATEGORY_CUSTOMER = 'customer';
    const CATEGORY_OPERATIONAL = 'operational';

    const DATA_TYPE_NUMBER = 'number';
    const DATA_TYPE_PERCENTAGE = 'percentage';
    const DATA_TYPE_CURRENCY = 'currency';
    const DATA_TYPE_COUNT = 'count';
    const DATA_TYPE_DURATION = 'duration';

    const AGGREGATION_SUM = 'sum';
    const AGGREGATION_AVG = 'avg';
    const AGGREGATION_COUNT = 'count';
    const AGGREGATION_MIN = 'min';
    const AGGREGATION_MAX = 'max';
    const AGGREGATION_LAST = 'last';

    public function dataPoints()
    {
        return $this->hasMany(AnalyticsDataPoint::class, 'metric_id');
    }

    public function widgets()
    {
        return $this->hasMany(DashboardWidget::class, 'metric_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function getCategoryTextAttribute(): string
    {
        return match($this->category) {
            self::CATEGORY_SALES => 'المبيعات',
            self::CATEGORY_INVENTORY => 'المخزون',
            self::CATEGORY_WAREHOUSE => 'المستودع',
            self::CATEGORY_FINANCIAL => 'المالي',
            self::CATEGORY_CUSTOMER => 'العملاء',
            self::CATEGORY_OPERATIONAL => 'التشغيلي',
            default => $this->category,
        };
    }

    public function getDataTypeTextAttribute(): string
    {
        return match($this->data_type) {
            self::DATA_TYPE_NUMBER => 'رقم',
            self::DATA_TYPE_PERCENTAGE => 'نسبة مئوية',
            self::DATA_TYPE_CURRENCY => 'عملة',
            self::DATA_TYPE_COUNT => 'عدد',
            self::DATA_TYPE_DURATION => 'مدة',
            default => $this->data_type,
        };
    }

    public function getLatestValueAttribute(): ?float
    {
        return $this->dataPoints()->latest('recorded_date')->value('value');
    }

    public function getPreviousValueAttribute(): ?float
    {
        return $this->dataPoints()
            ->where('recorded_date', '<', now()->subDay())
            ->latest('recorded_date')
            ->value('value');
    }

    public function getTrendAttribute(): string
    {
        $current = $this->getLatestValueAttribute();
        $previous = $this->getPreviousValueAttribute();

        if ($current === null || $previous === null) {
            return 'stable';
        }

        if ($current > $previous) {
            return 'up';
        } elseif ($current < $previous) {
            return 'down';
        }

        return 'stable';
    }

    public function getTrendPercentageAttribute(): float
    {
        $current = $this->getLatestValueAttribute();
        $previous = $this->getPreviousValueAttribute();

        if ($current === null || $previous === null || $previous == 0) {
            return 0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
