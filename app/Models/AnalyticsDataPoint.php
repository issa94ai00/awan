<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsDataPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_id',
        'warehouse_id',
        'channel_id',
        'recorded_date',
        'value',
        'dimensions',
        'metadata',
    ];

    protected $casts = [
        'recorded_date' => 'date',
        'value' => 'decimal:4',
        'dimensions' => 'array',
        'metadata' => 'array',
    ];

    public function metric()
    {
        return $this->belongsTo(AnalyticsMetric::class, 'metric_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function channel()
    {
        return $this->belongsTo(OrderChannel::class, 'channel_id');
    }

    public function scopeByMetric($query, $metricId)
    {
        return $query->where('metric_id', $metricId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('recorded_date', [$fromDate, $toDate]);
    }

    public function scopeForPeriod($query, $days = 30)
    {
        return $query->where('recorded_date', '>=', now()->subDays($days));
    }
}
