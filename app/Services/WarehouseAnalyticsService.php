<?php

namespace App\Services;

use App\Models\PickingList;
use App\Models\PackingList;
use App\Models\ShippingManifest;
use App\Models\CycleCount;
use App\Models\WarehouseBin;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsDataPoint;
use Illuminate\Support\Collection;

class WarehouseAnalyticsService
{
    /**
     * Get warehouse performance summary
     */
    public function getWarehousePerformance($warehouseId, $fromDate, $toDate): array
    {
        $pickingStats = $this->getPickingStatistics($warehouseId, $fromDate, $toDate);
        $packingStats = $this->getPackingStatistics($warehouseId, $fromDate, $toDate);
        $shippingStats = $this->getShippingStatistics($warehouseId, $fromDate, $toDate);

        return [
            'picking' => $pickingStats,
            'packing' => $packingStats,
            'shipping' => $shippingStats,
            'overall_efficiency' => $this->calculateOverallEfficiency($pickingStats, $packingStats, $shippingStats),
        ];
    }

    /**
     * Get picking statistics
     */
    public function getPickingStatistics($warehouseId, $fromDate, $toDate): array
    {
        $query = PickingList::where('warehouse_id', $warehouseId)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        $lists = $query->get();

        $totalLists = $lists->count();
        $completedLists = $lists->where('status', PickingList::STATUS_COMPLETED)->count();
        
        $avgCompletionTime = 0;
        if ($completedLists > 0) {
            $totalMinutes = $lists->where('status', PickingList::STATUS_COMPLETED)
                ->sum(function ($list) {
                    if (!$list->started_at || !$list->completed_at) {
                        return 0;
                    }
                    return $list->started_at->diffInMinutes($list->completed_at);
                });
            $avgCompletionTime = $totalMinutes / $completedLists;
        }

        $totalItems = $lists->sum('total_items');
        $pickedItems = $lists->sum('picked_items');

        return [
            'total_lists' => $totalLists,
            'completed_lists' => $completedLists,
            'completion_rate' => $totalLists > 0 ? ($completedLists / $totalLists) * 100 : 0,
            'total_items' => $totalItems,
            'picked_items' => $pickedItems,
            'pick_rate' => $totalItems > 0 ? ($pickedItems / $totalItems) * 100 : 0,
            'avg_completion_time_minutes' => round($avgCompletionTime, 2),
        ];
    }

    /**
     * Get packing statistics
     */
    public function getPackingStatistics($warehouseId, $fromDate, $toDate): array
    {
        $query = PackingList::where('warehouse_id', $warehouseId)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        $lists = $query->get();

        $totalLists = $lists->count();
        $completedLists = $lists->where('status', PackingList::STATUS_COMPLETED)->count();
        
        $avgCompletionTime = 0;
        if ($completedLists > 0) {
            $totalMinutes = $lists->where('status', PackingList::STATUS_COMPLETED)
                ->sum(function ($list) {
                    if (!$list->started_at || !$list->completed_at) {
                        return 0;
                    }
                    return $list->started_at->diffInMinutes($list->completed_at);
                });
            $avgCompletionTime = $totalMinutes / $completedLists;
        }

        $totalPackages = $lists->sum('total_packages');
        $totalWeight = $lists->sum('total_weight');

        return [
            'total_lists' => $totalLists,
            'completed_lists' => $completedLists,
            'completion_rate' => $totalLists > 0 ? ($completedLists / $totalLists) * 100 : 0,
            'total_packages' => $totalPackages,
            'total_weight_kg' => $totalWeight,
            'avg_completion_time_minutes' => round($avgCompletionTime, 2),
        ];
    }

    /**
     * Get shipping statistics
     */
    public function getShippingStatistics($warehouseId, $fromDate, $toDate): array
    {
        $query = ShippingManifest::where('warehouse_id', $warehouseId)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        $manifests = $query->get();

        $totalManifests = $manifests->count();
        $deliveredManifests = $manifests->where('status', ShippingManifest::STATUS_DELIVERED)->count();
        
        $onTimeDeliveries = 0;
        foreach ($manifests->where('status', ShippingManifest::STATUS_DELIVERED) as $manifest) {
            if ($manifest->actual_delivery && $manifest->estimated_delivery) {
                if ($manifest->actual_delivery->lte($manifest->estimated_delivery)) {
                    $onTimeDeliveries++;
                }
            }
        }

        return [
            'total_manifests' => $totalManifests,
            'delivered_manifests' => $deliveredManifests,
            'delivery_rate' => $totalManifests > 0 ? ($deliveredManifests / $totalManifests) * 100 : 0,
            'on_time_deliveries' => $onTimeDeliveries,
            'on_time_rate' => $deliveredManifests > 0 ? ($onTimeDeliveries / $deliveredManifests) * 100 : 0,
        ];
    }

    /**
     * Calculate overall warehouse efficiency
     */
    protected function calculateOverallEfficiency($pickingStats, $packingStats, $shippingStats): float
    {
        $pickingScore = $pickingStats['completion_rate'] * 0.4;
        $packingScore = $packingStats['completion_rate'] * 0.3;
        $shippingScore = $shippingStats['on_time_rate'] * 0.3;

        return round($pickingScore + $packingScore + $shippingScore, 2);
    }

    /**
     * Get bin utilization analysis
     */
    public function getBinUtilization($warehouseId): array
    {
        $bins = WarehouseBin::where('warehouse_id', $warehouseId)
            ->active()
            ->get();

        $totalBins = $bins->count();
        $fullBins = $bins->filter->isFull()->count();
        $emptyBins = $bins->filter->isEmpty()->count();
        
        $avgUtilization = $bins->avg(function ($bin) {
            return $bin->getUtilizationPercentageAttribute();
        }) ?? 0;

        $byZone = $bins->groupBy('zone')->map(function ($zoneBins) {
            return [
                'total_bins' => $zoneBins->count(),
                'avg_utilization' => $zoneBins->avg(function ($bin) {
                    return $bin->getUtilizationPercentageAttribute();
                }) ?? 0,
            ];
        });

        return [
            'total_bins' => $totalBins,
            'full_bins' => $fullBins,
            'empty_bins' => $emptyBins,
            'avg_utilization' => round($avgUtilization, 2),
            'by_zone' => $byZone->toArray(),
        ];
    }

    /**
     * Get cycle count accuracy
     */
    public function getCycleCountAccuracy($warehouseId, $fromDate, $toDate): array
    {
        $counts = CycleCount::where('warehouse_id', $warehouseId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('status', CycleCount::STATUS_COMPLETED)
            ->get();

        $totalCounts = $counts->count();
        $accurateCounts = $counts->where('variance_items', 0)->count();
        
        $totalItems = $counts->sum('total_items');
        $varianceItems = $counts->sum('variance_items');

        return [
            'total_counts' => $totalCounts,
            'accurate_counts' => $accurateCounts,
            'accuracy_rate' => $totalCounts > 0 ? ($accurateCounts / $totalCounts) * 100 : 0,
            'total_items_counted' => $totalItems,
            'variance_items' => $varianceItems,
            'variance_rate' => $totalItems > 0 ? ($varianceItems / $totalItems) * 100 : 0,
        ];
    }

    /**
     * Get picker performance ranking
     */
    public function getPickerPerformance($warehouseId, $fromDate, $toDate): Collection
    {
        $lists = PickingList::where('warehouse_id', $warehouseId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereNotNull('picker_id')
            ->get();

        return $lists->groupBy('picker_id')
            ->map(function ($pickerLists) {
                $completed = $pickerLists->where('status', PickingList::STATUS_COMPLETED);
                $totalItems = $pickerLists->sum('total_items');
                $pickedItems = $pickerLists->sum('picked_items');

                $avgTime = 0;
                if ($completed->count() > 0) {
                    $totalMinutes = $completed->sum(function ($list) {
                        if (!$list->started_at || !$list->completed_at) {
                            return 0;
                        }
                        return $list->started_at->diffInMinutes($list->completed_at);
                    });
                    $avgTime = $totalMinutes / $completed->count();
                }

                return [
                    'picker_id' => $pickerLists->first()->picker_id,
                    'total_lists' => $pickerLists->count(),
                    'completed_lists' => $completed->count(),
                    'completion_rate' => $pickerLists->count() > 0 ? ($completed->count() / $pickerLists->count()) * 100 : 0,
                    'total_items' => $totalItems,
                    'picked_items' => $pickedItems,
                    'pick_rate' => $totalItems > 0 ? ($pickedItems / $totalItems) * 100 : 0,
                    'avg_completion_time_minutes' => round($avgTime, 2),
                ];
            })
            ->sortByDesc('completion_rate')
            ->values();
    }

    /**
     * Record warehouse metrics
     */
    public function recordMetrics($warehouseId, $date = null): void
    {
        $date = $date ?? now()->toDateString();
        $fromDate = now()->subDay()->toDateString();
        $toDate = $date;

        $performance = $this->getWarehousePerformance($warehouseId, $fromDate, $toDate);
        $binUtilization = $this->getBinUtilization($warehouseId);

        // Overall efficiency metric
        $this->recordMetric('warehouse_efficiency', $performance['overall_efficiency'], $date, $warehouseId);

        // Bin utilization metric
        $this->recordMetric('bin_utilization', $binUtilization['avg_utilization'], $date, $warehouseId);

        // Picking completion rate
        $this->recordMetric('picking_completion_rate', $performance['picking']['completion_rate'], $date, $warehouseId);
    }

    /**
     * Record a single metric
     */
    protected function recordMetric($key, $value, $date, $warehouseId): void
    {
        $metric = AnalyticsMetric::where('metric_key', $key)->first();
        
        if (!$metric) {
            return;
        }

        AnalyticsDataPoint::updateOrCreate(
            [
                'metric_id' => $metric->id,
                'warehouse_id' => $warehouseId,
                'recorded_date' => $date,
            ],
            [
                'value' => $value,
            ]
        );
    }

    /**
     * Get warehouse capacity planning
     */
    public function getCapacityPlanning($warehouseId): array
    {
        $bins = WarehouseBin::where('warehouse_id', $warehouseId)->get();
        $utilization = $this->getBinUtilization($warehouseId);

        $totalCapacity = $bins->sum('capacity_value');
        $usedCapacity = $bins->sum('current_utilization');
        $availableCapacity = $totalCapacity - $usedCapacity;

        $recommendation = '';
        if ($utilization['avg_utilization'] > 90) {
            $recommendation = 'Critical: Consider expanding warehouse capacity or optimizing bin allocation';
        } elseif ($utilization['avg_utilization'] > 75) {
            $recommendation = 'Warning: Capacity is running high, monitor closely';
        } else {
            $recommendation = 'Good: Capacity utilization is within acceptable range';
        }

        return [
            'total_capacity' => $totalCapacity,
            'used_capacity' => $usedCapacity,
            'available_capacity' => $availableCapacity,
            'utilization_percentage' => $utilization['avg_utilization'],
            'recommendation' => $recommendation,
        ];
    }
}
