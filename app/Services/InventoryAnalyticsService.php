<?php

namespace App\Services;

use App\Models\Product;
use App\Models\WarehouseInventory;
use App\Models\StockMovement;
use App\Models\ReorderAlert;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsDataPoint;
use Illuminate\Support\Collection;

class InventoryAnalyticsService
{
    /**
     * Get inventory summary
     */
    public function getInventorySummary($warehouseId = null): array
    {
        $query = WarehouseInventory::query();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $inventory = $query->get();

        return [
            'total_products' => $inventory->pluck('product_id')->unique()->count(),
            'total_stock' => $inventory->sum('stock_quantity'),
            'total_value' => $inventory->sum(function ($item) {
                return $item->stock_quantity * ($item->product?->cost_price ?? 0);
            }),
            'low_stock_items' => $inventory->where('available_stock', '<=', 0)->count(),
            'overstock_items' => $inventory->where('available_stock', '>', function ($item) {
                return $item->product?->max_stock ?? 999999;
            })->count(),
        ];
    }

    /**
     * Get inventory turnover rate
     */
    public function getInventoryTurnover($days = 30, $warehouseId = null): array
    {
        $startDate = now()->subDays($days);
        
        $query = StockMovement::where('movement_type', StockMovement::TYPE_OUT)
            ->where('created_at', '>=', $startDate);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $outboundMovements = $query->get();
        
        $totalSold = $outboundMovements->sum('quantity');
        $averageInventory = WarehouseInventory::when($warehouseId, function ($q) use ($warehouseId) {
            return $q->where('warehouse_id', $warehouseId);
        })->avg('stock_quantity') ?? 0;

        $turnoverRate = $averageInventory > 0 ? ($totalSold / $averageInventory) : 0;

        return [
            'period_days' => $days,
            'total_sold' => $totalSold,
            'average_inventory' => $averageInventory,
            'turnover_rate' => $turnoverRate,
            'annualized_turnover' => $turnoverRate * (365 / $days),
        ];
    }

    /**
     * Get slow-moving inventory
     */
    public function getSlowMovingInventory($days = 90, $warehouseId = null): Collection
    {
        $cutoffDate = now()->subDays($days);
        
        $query = WarehouseInventory::with('product')
            ->whereHas('product', function ($q) {
                $q->where('status', 'active');
            });

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $inventory = $query->get();

        $slowMoving = $inventory->filter(function ($item) use ($cutoffDate) {
            $lastMovement = StockMovement::where('product_id', $item->product_id)
                ->where('warehouse_id', $item->warehouse_id)
                ->where('created_at', '>=', $cutoffDate)
                ->where('movement_type', StockMovement::TYPE_OUT)
                ->exists();

            return !$lastMovement && $item->stock_quantity > 0;
        });

        return $slowMoving->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'sku' => $item->product?->sku,
                'stock_quantity' => $item->stock_quantity,
                'value' => $item->stock_quantity * ($item->product?->cost_price ?? 0),
                'days_in_stock' => $item->created_at?->diffInDays(now()) ?? 0,
            ];
        })->sortByDesc('value')->values();
    }

    /**
     * Get stockout analysis
     */
    public function getStockoutAnalysis($days = 30, $warehouseId = null): array
    {
        $startDate = now()->subDays($days);
        
        $alerts = ReorderAlert::where('created_at', '>=', $startDate)
            ->when($warehouseId, function ($q) use ($warehouseId) {
                return $q->where('warehouse_id', $warehouseId);
            })
            ->with('product')
            ->get();

        $resolved = $alerts->where('status', 'resolved')->count();
        $pending = $alerts->where('status', 'pending')->count();
        $critical = $alerts->where('severity', 'critical')->count();

        return [
            'total_alerts' => $alerts->count(),
            'resolved' => $resolved,
            'pending' => $pending,
            'critical' => $critical,
            'resolution_rate' => $alerts->count() > 0 ? ($resolved / $alerts->count()) * 100 : 0,
        ];
    }

    /**
     * Get inventory valuation
     */
    public function getInventoryValuation($warehouseId = null): array
    {
        $query = WarehouseInventory::with('product');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $inventory = $query->get();

        $totalValue = 0;
        $totalCost = 0;
        $categories = [];

        foreach ($inventory as $item) {
            $value = $item->stock_quantity * ($item->product?->price ?? 0);
            $cost = $item->stock_quantity * ($item->product?->cost_price ?? 0);
            
            $totalValue += $value;
            $totalCost += $cost;

            $category = $item->product?->category?->name ?? 'Uncategorized';
            if (!isset($categories[$category])) {
                $categories[$category] = 0;
            }
            $categories[$category] += $value;
        }

        return [
            'total_value' => $totalValue,
            'total_cost' => $totalCost,
            'gross_margin' => $totalCost > 0 ? (($totalValue - $totalCost) / $totalCost) * 100 : 0,
            'by_category' => collect($categories)->sortDesc()->toArray(),
        ];
    }

    /**
     * Get ABC analysis
     */
    public function getABCAnalysis($warehouseId = null): array
    {
        $query = WarehouseInventory::with('product');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $inventory = $query->get();

        // Calculate annual usage value for each product
        $productValues = $inventory->map(function ($item) {
            $recentMovements = StockMovement::where('product_id', $item->product_id)
                ->where('warehouse_id', $item->warehouse_id)
                ->where('movement_type', StockMovement::TYPE_OUT)
                ->where('created_at', '>=', now()->subYear())
                ->sum('quantity');

            $annualValue = $recentMovements * ($item->product?->cost_price ?? 0);
            
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'annual_usage' => $recentMovements,
                'annual_value' => $annualValue,
                'stock_quantity' => $item->stock_quantity,
            ];
        })->sortByDesc('annual_value');

        $totalValue = $productValues->sum('annual_value');
        $cumulativeValue = 0;

        $abcClassification = $productValues->map(function ($item) use (&$cumulativeValue, $totalValue) {
            $cumulativeValue += $item['annual_value'];
            $cumulativePercentage = $totalValue > 0 ? ($cumulativeValue / $totalValue) * 100 : 0;

            if ($cumulativePercentage <= 80) {
                $item['category'] = 'A';
            } elseif ($cumulativePercentage <= 95) {
                $item['category'] = 'B';
            } else {
                $item['category'] = 'C';
            }

            return $item;
        });

        return [
            'a_items' => $abcClassification->where('category', 'A')->count(),
            'b_items' => $abcClassification->where('category', 'B')->count(),
            'c_items' => $abcClassification->where('category', 'C')->count(),
            'details' => $abcClassification->values()->toArray(),
        ];
    }

    /**
     * Record inventory metrics
     */
    public function recordMetrics($warehouseId = null, $date = null): void
    {
        $date = $date ?? now()->toDateString();
        $summary = $this->getInventorySummary($warehouseId);

        // Total stock metric
        $this->recordMetric('total_stock', $summary['total_stock'], $date, $warehouseId);

        // Total value metric
        $this->recordMetric('inventory_value', $summary['total_value'], $date, $warehouseId);

        // Low stock items metric
        $this->recordMetric('low_stock_items', $summary['low_stock_items'], $date, $warehouseId);
    }

    /**
     * Record a single metric
     */
    protected function recordMetric($key, $value, $date, $warehouseId = null): void
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
     * Get inventory health score
     */
    public function getInventoryHealthScore($warehouseId = null): array
    {
        $summary = $this->getInventorySummary($warehouseId);
        $stockoutAnalysis = $this->getStockoutAnalysis(30, $warehouseId);
        $turnover = $this->getInventoryTurnover(30, $warehouseId);

        // Calculate health score (0-100)
        $score = 100;

        // Deduct for low stock items
        $lowStockPenalty = min(30, ($summary['low_stock_items'] / max(1, $summary['total_products'])) * 100);
        $score -= $lowStockPenalty;

        // Deduct for pending stockouts
        $stockoutPenalty = min(20, ($stockoutAnalysis['pending'] / max(1, $stockoutAnalysis['total_alerts'])) * 100);
        $score -= $stockoutPenalty;

        // Bonus for good turnover
        if ($turnover['turnover_rate'] > 2) {
            $score = min(100, $score + 10);
        }

        return [
            'health_score' => max(0, round($score)),
            'total_products' => $summary['total_products'],
            'low_stock_items' => $summary['low_stock_items'],
            'stockout_alerts' => $stockoutAnalysis['pending'],
            'turnover_rate' => $turnover['turnover_rate'],
            'recommendations' => $this->getHealthRecommendations($score, $summary, $stockoutAnalysis),
        ];
    }

    /**
     * Get health recommendations
     */
    protected function getHealthRecommendations($score, $summary, $stockoutAnalysis): array
    {
        $recommendations = [];

        if ($score < 60) {
            $recommendations[] = 'Critical: Review inventory levels and reorder points immediately';
        }

        if ($summary['low_stock_items'] > 0) {
            $recommendations[] = "Action required: {$summary['low_stock_items']} items are out of stock";
        }

        if ($stockoutAnalysis['critical'] > 0) {
            $recommendations[] = "Urgent: {$stockoutAnalysis['critical']} critical stockout alerts need attention";
        }

        if ($score >= 80) {
            $recommendations[] = 'Good: Inventory health is optimal';
        }

        return $recommendations;
    }
}
