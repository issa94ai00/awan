<?php

namespace App\Services;

use App\Models\Report;
use App\Models\SalesOrder;
use App\Models\Product;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ReportBuilderService
{
    /**
     * Execute a report based on its configuration
     */
    public function executeReport(Report $report, array $filters = []): array
    {
        $queryConfig = $report->query_config;
        $table = $queryConfig['table'] ?? null;
        $columns = $queryConfig['columns'] ?? ['*'];
        $groupBy = $queryConfig['group_by'] ?? null;
        $aggregations = $queryConfig['aggregations'] ?? [];

        if (!$table) {
            return ['error' => 'No table specified in query configuration'];
        }

        $query = $this->buildQuery($table, $columns, $filters);

        if ($groupBy) {
            $query->groupBy($groupBy);
        }

        foreach ($aggregations as $agg) {
            $field = $agg['field'] ?? '*';
            $function = $agg['function'] ?? 'sum';
            $alias = $agg['alias'] ?? "{$function}_{$field}";

            $query->selectRaw("{$function}({$field}) as {$alias}");
        }

        $results = $query->get();

        return [
            'report_id' => $report->id,
            'report_name' => $report->name,
            'format' => $report->format,
            'data' => $results,
            'columns' => $report->column_config ?? $columns,
            'chart_config' => $report->chart_config,
        ];
    }

    /**
     * Build query based on table and filters
     */
    protected function buildQuery($table, $columns, $filters)
    {
        $query = DB::table($table)->select($columns);

        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * Generate a sales report
     */
    public function generateSalesReport($fromDate, $toDate, $groupBy = 'day'): array
    {
        $query = SalesOrder::whereBetween('order_date', [$fromDate, $toDate])
            ->whereNotIn('status', [SalesOrder::STATUS_CANCELLED]);

        if ($groupBy === 'day') {
            $results = $query->selectRaw('DATE(order_date) as period, COUNT(*) as orders, SUM(total_amount) as revenue')
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        } elseif ($groupBy === 'week') {
            $results = $query->selectRaw('YEARWEEK(order_date) as period, COUNT(*) as orders, SUM(total_amount) as revenue')
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        } elseif ($groupBy === 'month') {
            $results = $query->selectRaw('DATE_FORMAT(order_date, "%Y-%m") as period, COUNT(*) as orders, SUM(total_amount) as revenue')
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        } else {
            $results = $query->get();
        }

        return [
            'type' => 'sales',
            'group_by' => $groupBy,
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'data' => $results,
            'summary' => [
                'total_orders' => $results->sum('orders'),
                'total_revenue' => $results->sum('revenue'),
                'avg_order_value' => $results->sum('orders') > 0 ? $results->sum('revenue') / $results->sum('orders') : 0,
            ],
        ];
    }

    /**
     * Generate an inventory report
     */
    public function generateInventoryReport($warehouseId = null): array
    {
        $query = WarehouseInventory::with('product', 'product.category');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $inventory = $query->get();

        $byCategory = $inventory->groupBy(function ($item) {
            return $item->product?->category?->name ?? 'Uncategorized';
        })->map(function ($categoryItems) {
            return [
                'category' => $categoryItems->first()->product?->category?->name ?? 'Uncategorized',
                'total_products' => $categoryItems->count(),
                'total_stock' => $categoryItems->sum('stock_quantity'),
                'total_value' => $categoryItems->sum(function ($item) {
                    return $item->stock_quantity * ($item->product?->cost_price ?? 0);
                }),
            ];
        })->values();

        return [
            'type' => 'inventory',
            'warehouse_id' => $warehouseId,
            'data' => $byCategory,
            'summary' => [
                'total_products' => $inventory->pluck('product_id')->unique()->count(),
                'total_stock' => $inventory->sum('stock_quantity'),
                'total_value' => $inventory->sum(function ($item) {
                    return $item->stock_quantity * ($item->product?->cost_price ?? 0);
                }),
            ],
        ];
    }

    /**
     * Generate a product performance report
     */
    public function generateProductPerformanceReport($fromDate, $toDate, $limit = 20): array
    {
        $results = \Illuminate\Support\Facades\DB::table('sales_order_items as soi')
            ->join('sales_orders as so', 'soi.sales_order_id', '=', 'so.id')
            ->leftJoin('products as p', 'soi.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->whereBetween('so.order_date', [$fromDate, $toDate])
            ->where('so.status', '!=', SalesOrder::STATUS_CANCELLED)
            // No `p.name` column exists — see the same fix in SalesAnalyticsService.
            ->selectRaw('soi.product_id, p.name_ar, p.name_en, p.sku, c.name_ar as cat_ar, c.name_en as cat_en, SUM(soi.quantity) as quantity_sold, SUM(soi.total) as revenue, COUNT(so.id) as orders')
            ->groupBy('soi.product_id', 'p.name_ar', 'p.name_en', 'p.sku', 'c.name_ar', 'c.name_en')
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
                    $productName = $item->name_ar ?: ($item->name_en ?: 'Unknown');
                }

                // `??` only catches null, so a product with an empty-string name
                // in the reading language rendered as blank rather than falling
                // through to the other language.
                $categoryName = 'Uncategorized';
                if ($locale === 'ar' && !empty($item->cat_ar)) {
                    $categoryName = $item->cat_ar;
                } elseif ($locale === 'en' && !empty($item->cat_en)) {
                    $categoryName = $item->cat_en;
                } else {
                    $categoryName = $item->cat_ar ?: ($item->cat_en ?: 'Uncategorized');
                }

                return [
                    'product_id' => $item->product_id,
                    'product_name' => $productName,
                    'sku' => $item->sku,
                    'category' => $categoryName,
                    'quantity_sold' => (int) $item->quantity_sold,
                    'revenue' => (float) $item->revenue,
                    'orders' => (int) $item->orders,
                ];
            });

        return [
            'type' => 'product_performance',
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'data' => $results->values()->toArray(),
            'summary' => [
                'total_products' => $results->count(),
                'total_revenue' => $results->sum('revenue'),
                'total_quantity' => $results->sum('quantity_sold'),
            ],
        ];
    }

    /**
     * Generate a customer report
     */
    public function generateCustomerReport($fromDate, $toDate, $limit = 20): array
    {
        $orders = SalesOrder::whereBetween('order_date', [$fromDate, $toDate])
            ->whereNotIn('status', [SalesOrder::STATUS_CANCELLED])
            ->with('customer')
            ->get();

        $customerPerformance = $orders->groupBy('customer_id')
            ->map(function ($customerOrders) {
                $customer = $customerOrders->first()->customer;
                return [
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name ?? 'Unknown',
                    'customer_email' => $customer?->email,
                    'total_orders' => $customerOrders->count(),
                    'total_spent' => $customerOrders->sum('total_amount'),
                    'avg_order_value' => $customerOrders->sum('total_amount') / $customerOrders->count(),
                    'last_order_date' => $customerOrders->max('order_date'),
                ];
            })
            ->sortByDesc('total_spent')
            ->take($limit)
            ->values();

        return [
            'type' => 'customer',
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'data' => $customerPerformance,
            'summary' => [
                'total_customers' => $customerPerformance->count(),
                'total_revenue' => $customerPerformance->sum('total_spent'),
                'avg_customer_value' => $customerPerformance->avg('total_spent'),
            ],
        ];
    }

    /**
     * Generate a low stock report
     */
    public function generateLowStockReport($warehouseId = null): array
    {
        $query = WarehouseInventory::with('product', 'product.category');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $lowStockItems = $query->get()
            ->filter(function ($item) {
                $minStock = $item->product?->min_stock ?? 0;
                return $item->available_stock <= $minStock;
            })
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'category' => $item->product?->category?->name,
                    'current_stock' => $item->available_stock,
                    'min_stock' => $item->product?->min_stock,
                    'reorder_point' => $item->product?->reorder_point,
                    'warehouse_id' => $item->warehouse_id,
                    'status' => $item->available_stock == 0 ? 'out_of_stock' : 'low_stock',
                ];
            })
            ->sortBy('status')
            ->values();

        return [
            'type' => 'low_stock',
            'warehouse_id' => $warehouseId,
            'data' => $lowStockItems,
            'summary' => [
                'total_items' => $lowStockItems->count(),
                'out_of_stock' => $lowStockItems->where('status', 'out_of_stock')->count(),
                'low_stock' => $lowStockItems->where('status', 'low_stock')->count(),
            ],
        ];
    }

    /**
     * Rows rendered as CSV text, ready to stream or to write.
     *
     * Opens with a UTF-8 BOM because Excel on Windows otherwise reads the file
     * in the system codepage, which turns every Arabic product name into
     * mojibake — the single most likely thing to go wrong with an export from
     * this system.
     *
     * @param  iterable<int, array<string, mixed>|object>  $rows
     */
    public function toCsvString(iterable $rows): string
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows);

        if ($rows === []) {
            return "\xEF\xBB\xBF";
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        $first = is_object($rows[array_key_first($rows)])
            ? get_object_vars($rows[array_key_first($rows)])
            : $rows[array_key_first($rows)];
        fputcsv($handle, array_keys($first));

        foreach ($rows as $row) {
            $row = is_object($row) ? get_object_vars($row) : $row;
            // Nested values (a breakdown inside a row) would render as "Array";
            // JSON keeps them readable in a cell.
            $row = array_map(
                fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                $row
            );
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Export report to CSV
     *
     * Creates the export directory first: it is not in the repository, so this
     * method fatally failed on `fopen` for any install that had never made it
     * by hand.
     */
    public function exportToCsv(array $reportData, $filename = null): string
    {
        $data = $reportData['data'] ?? [];

        if (empty($data) || !is_iterable($data)) {
            return '';
        }

        $filename = $filename ?? 'report_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $directory = storage_path('app/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filepath = $directory . '/' . $filename;
        file_put_contents($filepath, $this->toCsvString($data));

        return $filepath;
    }

    /**
     * Export report to JSON
     */
    public function exportToJson(array $reportData, $filename = null): string
    {
        $filename = $filename ?? 'report_' . now()->format('Y-m-d_H-i-s') . '.json';
        $filepath = storage_path('app/exports/' . $filename);

        file_put_contents($filepath, json_encode($reportData, JSON_PRETTY_PRINT));

        return $filepath;
    }

    /**
     * Get available report templates
     */
    public function getReportTemplates(): array
    {
        return [
            [
                'id' => 'sales_daily',
                'name' => 'Daily Sales Report',
                'name_ar' => 'تقرير المبيعات اليومي',
                'type' => 'sales',
                'description' => 'Daily sales summary with orders and revenue',
                'query_config' => [
                    'table' => 'sales_orders',
                    'columns' => ['id', 'order_number', 'order_date', 'total_amount', 'status'],
                    'group_by' => 'DATE(order_date)',
                ],
            ],
            [
                'id' => 'inventory_summary',
                'name' => 'Inventory Summary',
                'name_ar' => 'ملخص المخزون',
                'type' => 'inventory',
                'description' => 'Current inventory levels by category',
                'query_config' => [
                    'table' => 'warehouse_inventory',
                    'columns' => ['product_id', 'warehouse_id', 'stock_quantity', 'available_stock'],
                ],
            ],
            [
                'id' => 'product_performance',
                'name' => 'Product Performance',
                'name_ar' => 'أداء المنتجات',
                'type' => 'sales',
                'description' => 'Top performing products by revenue',
                'query_config' => [
                    'table' => 'sales_order_items',
                    'columns' => ['product_id', 'quantity', 'total_price'],
                    'aggregations' => [
                        ['field' => 'quantity', 'function' => 'sum', 'alias' => 'total_quantity'],
                        ['field' => 'total_price', 'function' => 'sum', 'alias' => 'total_revenue'],
                    ],
                ],
            ],
            [
                'id' => 'low_stock',
                'name' => 'Low Stock Alert',
                'name_ar' => 'تنبيه انخفاض المخزون',
                'type' => 'inventory',
                'description' => 'Products below minimum stock level',
                'query_config' => [
                    'table' => 'warehouse_inventory',
                    'columns' => ['product_id', 'warehouse_id', 'available_stock'],
                ],
            ],
        ];
    }

    /**
     * Validate report configuration
     */
    public function validateReportConfig(array $config): array
    {
        $errors = [];

        if (!isset($config['table'])) {
            $errors[] = 'Table is required in query configuration';
        }

        if (!isset($config['columns']) || empty($config['columns'])) {
            $errors[] = 'At least one column must be specified';
        }

        if (isset($config['aggregations'])) {
            foreach ($config['aggregations'] as $agg) {
                if (!isset($agg['function'])) {
                    $errors[] = 'Aggregation function is required';
                }
                if (!in_array($agg['function'], ['sum', 'avg', 'count', 'min', 'max'])) {
                    $errors[] = 'Invalid aggregation function: ' . $agg['function'];
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
