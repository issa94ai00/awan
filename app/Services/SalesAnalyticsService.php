<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\OrderChannel;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsDataPoint;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesAnalyticsService
{
    /**
     * Get sales summary for a period
     */
    public function getSalesSummary($fromDate, $toDate, $channelId = null, $warehouseId = null): array
    {
        $query = SalesOrder::whereBetween('order_date', [$fromDate, $toDate]);

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        if ($warehouseId) {
            $query->where('fulfillment_warehouse_id', $warehouseId);
        }

        $orders = $query->get();

        return [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total_amount'),
            'average_order_value' => $orders->count() > 0 ? $orders->sum('total_amount') / $orders->count() : 0,
            'completed_orders' => $orders->where('status', SalesOrder::STATUS_DELIVERED)->count(),
            'pending_orders' => $orders->whereIn('status', [SalesOrder::STATUS_PENDING, SalesOrder::STATUS_CONFIRMED])->count(),
            'cancelled_orders' => $orders->where('status', SalesOrder::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * Get sales trend data for charts
     */
    public function getSalesTrend($days = 30, $groupBy = 'day'): array
    {
        $startDate = now()->subDays($days);
        $orders = SalesOrder::where('order_date', '>=', $startDate)
            ->whereNotIn('status', [SalesOrder::STATUS_CANCELLED])
            ->get();

        $trendData = [];

        if ($groupBy === 'day') {
            for ($i = $days; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $dayOrders = $orders->where('order_date', $date);
                
                $trendData[] = [
                    'date' => $date,
                    'revenue' => $dayOrders->sum('total_amount'),
                    'orders' => $dayOrders->count(),
                ];
            }
        } elseif ($groupBy === 'week') {
            $weeks = ceil($days / 7);
            for ($i = $weeks; $i >= 0; $i--) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $weekEnd = now()->subWeeks($i)->endOfWeek();
                $weekOrders = $orders->whereBetween('order_date', [$weekStart, $weekEnd]);
                
                $trendData[] = [
                    'period' => $weekStart->format('Y-m-d') . ' to ' . $weekEnd->format('Y-m-d'),
                    'revenue' => $weekOrders->sum('total_amount'),
                    'orders' => $weekOrders->count(),
                ];
            }
        }

        return $trendData;
    }

    /**
     * Get sales by channel
     */
    public function getSalesByChannel($fromDate, $toDate): Collection
    {
        $orders = SalesOrder::whereBetween('order_date', [$fromDate, $toDate])
            ->whereNotIn('status', [SalesOrder::STATUS_CANCELLED])
            ->get();

        return $orders->groupBy('channel_id')
            ->map(function ($channelOrders) {
                $channel = $channelOrders->first()->channel;
                return [
                    'channel_id' => $channel?->id,
                    'channel_name' => $channel?->name ?? 'Direct',
                    'revenue' => $channelOrders->sum('total_amount'),
                    'orders' => $channelOrders->count(),
                    'percentage' => 0, // Will be calculated
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * Get top selling products
     */
    public function getTopSellingProducts($fromDate, $toDate, $limit = 10): array
    {
        return \Illuminate\Support\Facades\DB::table('sales_order_items as soi')
            ->join('sales_orders as so', 'soi.sales_order_id', '=', 'so.id')
            ->leftJoin('products as p', 'soi.product_id', '=', 'p.id')
            ->whereBetween('so.order_date', [$fromDate, $toDate])
            ->where('so.status', '!=', SalesOrder::STATUS_CANCELLED)
            ->selectRaw('soi.product_id, p.name, p.name_ar, p.name_en, SUM(soi.quantity) as quantity, SUM(soi.total_price) as revenue')
            ->groupBy('soi.product_id', 'p.name', 'p.name_ar', 'p.name_en')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $locale = app()->getLocale();
                if ($locale === 'ar' && !empty($item->name_ar)) {
                    $productName = $item->name_ar;
                } elseif ($locale === 'en' && !empty($item->name_en)) {
                    $productName = $item->name_en;
                } else {
                    $productName = $item->name ?? ($item->name_ar ?? ($item->name_en ?? 'Unknown'));
                }
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $productName,
                    'quantity' => (int) $item->quantity,
                    'revenue' => (float) $item->revenue,
                ];
            })
            ->toArray();
    }

    /**
     * Get customer analytics
     */
    public function getCustomerAnalytics($fromDate, $toDate): array
    {
        $orders = SalesOrder::whereBetween('order_date', [$fromDate, $toDate])
            ->whereNotIn('status', [SalesOrder::STATUS_CANCELLED])
            ->get();

        $newCustomers = Customer::whereBetween('created_at', [$fromDate, $toDate])->count();
        $returningCustomers = $orders->pluck('customer_id')->unique()->count();

        $customerLTV = $orders->groupBy('customer_id')
            ->map(function ($customerOrders) {
                return $customerOrders->sum('total_amount');
            })
            ->avg() ?? 0;

        return [
            'new_customers' => $newCustomers,
            'returning_customers' => $returningCustomers,
            'average_ltv' => $customerLTV,
            'repeat_purchase_rate' => $returningCustomers > 0 ? 
                ($orders->count() / $returningCustomers) : 0,
        ];
    }

    /**
     * Forecast sales using simple moving average
     */
    public function forecastSales($days = 30, $forecastDays = 7): array
    {
        $historicalData = $this->getSalesTrend($days, 'day');
        
        // Calculate moving average
        $window = min(7, count($historicalData));
        $recentData = array_slice($historicalData, -$window);
        
        $avgDailyRevenue = collect($recentData)->avg('revenue') ?? 0;
        $avgDailyOrders = collect($recentData)->avg('orders') ?? 0;

        $forecast = [];
        for ($i = 1; $i <= $forecastDays; $i++) {
            $forecastDate = now()->addDays($i)->format('Y-m-d');
            $forecast[] = [
                'date' => $forecastDate,
                'predicted_revenue' => $avgDailyRevenue,
                'predicted_orders' => $avgDailyOrders,
            ];
        }

        return [
            'historical' => $historicalData,
            'forecast' => $forecast,
            'confidence' => 'medium',
        ];
    }

    /**
     * Record sales metrics
     */
    public function recordMetrics($date = null): void
    {
        $date = $date ?? now()->toDateString();
        $summary = $this->getSalesSummary(
            $date,
            $date
        );

        // Revenue metric
        $this->recordMetric('total_revenue', $summary['total_revenue'], $date);

        // Orders metric
        $this->recordMetric('total_orders', $summary['total_orders'], $date);

        // Average order value
        $this->recordMetric('avg_order_value', $summary['average_order_value'], $date);
    }

    /**
     * Record a single metric
     */
    protected function recordMetric($key, $value, $date): void
    {
        $metric = AnalyticsMetric::where('metric_key', $key)->first();
        
        if (!$metric) {
            return;
        }

        AnalyticsDataPoint::updateOrCreate(
            [
                'metric_id' => $metric->id,
                'recorded_date' => $date,
            ],
            [
                'value' => $value,
            ]
        );
    }

    /**
     * Get conversion funnel data
     */
    public function getConversionFunnel($fromDate, $toDate): array
    {
        $totalOrders = SalesOrder::whereBetween('order_date', [$fromDate, $toDate])->count();
        $confirmedOrders = SalesOrder::whereBetween('order_date', [$fromDate, $toDate])
            ->where('status', SalesOrder::STATUS_CONFIRMED)
            ->count();
        $deliveredOrders = SalesOrder::whereBetween('order_date', [$fromDate, $toDate])
            ->where('status', SalesOrder::STATUS_DELIVERED)
            ->count();

        return [
            'orders' => $totalOrders,
            'confirmed' => $confirmedOrders,
            'delivered' => $deliveredOrders,
            'conversion_rate' => $totalOrders > 0 ? ($confirmedOrders / $totalOrders) * 100 : 0,
            'fulfillment_rate' => $confirmedOrders > 0 ? ($deliveredOrders / $confirmedOrders) * 100 : 0,
        ];
    }
}
