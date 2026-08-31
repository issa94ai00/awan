<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsDataPoint;
use App\Models\Report;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Services\SalesAnalyticsService;
use App\Services\InventoryAnalyticsService;
use App\Services\WarehouseAnalyticsService;
use App\Services\FinancialAnalyticsService;
use App\Services\VisitorAnalyticsService;
use App\Services\PeriodComparison;
use App\Services\ReportBuilderService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    protected SalesAnalyticsService $salesAnalytics;
    protected InventoryAnalyticsService $inventoryAnalytics;
    protected WarehouseAnalyticsService $warehouseAnalytics;
    protected FinancialAnalyticsService $financialAnalytics;
    protected VisitorAnalyticsService $visitorAnalytics;

    public function __construct(
        SalesAnalyticsService $salesAnalytics,
        InventoryAnalyticsService $inventoryAnalytics,
        WarehouseAnalyticsService $warehouseAnalytics,
        FinancialAnalyticsService $financialAnalytics,
        VisitorAnalyticsService $visitorAnalytics
    ) {
        $this->salesAnalytics = $salesAnalytics;
        $this->inventoryAnalytics = $inventoryAnalytics;
        $this->warehouseAnalytics = $warehouseAnalytics;
        $this->financialAnalytics = $financialAnalytics;
        $this->visitorAnalytics = $visitorAnalytics;
    }

    // ==================== Overview ====================

    /**
     * The headline figures for the BI landing screen, each against its own past.
     *
     * The screen used to hold these numbers as literals — 150,000 in revenue and
     * 500 products on every install — beside trend percentages that were equally
     * invented. One endpoint now answers the whole card row so the page has a
     * single source and one loading state instead of four.
     *
     * Composed from the existing domain services rather than new queries: the
     * analysis already exists, it simply was never called.
     */
    public function getOverview(Request $request)
    {
        $toDate = $request->to_date ?? now()->toDateString();
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $warehouseId = $request->warehouse_id;

        $previous = PeriodComparison::previousWindow($fromDate, $toDate);

        $sales = $this->salesAnalytics->getSalesSummary($fromDate, $toDate, null, $warehouseId);
        $salesBefore = $this->salesAnalytics->getSalesSummary($previous['from'], $previous['to'], null, $warehouseId);

        $financial = $this->financialAnalytics->getFinancialSummary($fromDate, $toDate);
        $financialBefore = $this->financialAnalytics->getFinancialSummary($previous['from'], $previous['to']);

        $inventory = $this->inventoryAnalytics->getInventorySummary($warehouseId);
        $capacity = $this->warehouseAnalytics->getCapacityPlanning($warehouseId);

        return response()->json([
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'previous_from' => $previous['from'],
                'previous_to' => $previous['to'],
            ],
            'revenue' => PeriodComparison::compare(
                $sales['total_revenue'] ?? 0,
                $salesBefore['total_revenue'] ?? 0
            ),
            'orders' => PeriodComparison::compare(
                $sales['total_orders'] ?? 0,
                $salesBefore['total_orders'] ?? 0
            ),
            'gross_margin' => PeriodComparison::compare(
                $financial['gross_margin'] ?? 0,
                $financialBefore['gross_margin'] ?? 0
            ),
            // Stock levels are a reading of right now, not of a date range, so
            // they are reported as-is rather than dressed up with a comparison
            // the query cannot support.
            'inventory' => [
                'total_products' => $inventory['total_products'] ?? 0,
                'total_value' => $inventory['total_value'] ?? 0,
                'low_stock_items' => $inventory['low_stock_items'] ?? 0,
            ],
            'warehouse' => [
                'utilization_percentage' => $capacity['utilization_percentage'] ?? 0,
                'total_capacity' => $capacity['total_capacity'] ?? 0,
                'used_capacity' => $capacity['used_capacity'] ?? 0,
            ],
        ]);
    }

    /**
     * A domain's detail rows as a CSV download.
     *
     * The export buttons on these screens previously showed "export started"
     * and did nothing at all. This returns an actual file.
     *
     * Streamed rather than written to disk: the rows are already in memory, and
     * a temp file per click is a cleanup job nobody would remember to write.
     */
    public function export(Request $request, ReportBuilderService $reports, string $domain): StreamedResponse
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();
        $warehouseId = $request->warehouse_id;

        $rows = match ($domain) {
            'sales' => $this->salesAnalytics->getTopSellingProducts($fromDate, $toDate, 500),
            'inventory' => $this->inventoryAnalytics->getABCAnalysis($warehouseId)['details'] ?? [],
            'warehouse' => $this->warehouseAnalytics->getPickerPerformance($warehouseId, $fromDate, $toDate),
            'financial' => $this->financialAnalytics->getRevenueByCategory($fromDate, $toDate),
            'visitors' => $this->visitorAnalytics->exportRows($fromDate, $toDate, $request->only(['search', 'device_type', 'browser', 'is_bot'])),
            default => abort(404, 'Unknown analytics domain.'),
        };

        $csv = $reports->toCsvString($rows);
        $filename = "analytics-{$domain}-{$fromDate}-to-{$toDate}.csv";

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                // Exposed so the browser fetch layer can read the filename back;
                // a same-origin XHR cannot see it otherwise.
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]
        );
    }

    // ==================== Sales Analytics ====================

    public function getSalesSummary(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();
        $channelId = $request->channel_id;
        $warehouseId = $request->warehouse_id;

        $summary = $this->salesAnalytics->getSalesSummary($fromDate, $toDate, $channelId, $warehouseId);

        return response()->json($summary);
    }

    public function getSalesTrend(Request $request)
    {
        $days = $request->days ?? 30;
        $groupBy = $request->group_by ?? 'day';

        $trend = $this->salesAnalytics->getSalesTrend($days, $groupBy);

        return response()->json($trend);
    }

    public function getSalesByChannel(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $byChannel = $this->salesAnalytics->getSalesByChannel($fromDate, $toDate);

        return response()->json($byChannel);
    }

    public function getTopSellingProducts(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();
        $limit = $request->limit ?? 10;

        $topProducts = $this->salesAnalytics->getTopSellingProducts($fromDate, $toDate, $limit);

        return response()->json($topProducts);
    }

    public function getCustomerAnalytics(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $analytics = $this->salesAnalytics->getCustomerAnalytics($fromDate, $toDate);

        return response()->json($analytics);
    }

    public function forecastSales(Request $request)
    {
        $days = $request->days ?? 30;
        $forecastDays = $request->forecast_days ?? 7;

        $forecast = $this->salesAnalytics->forecastSales($days, $forecastDays);

        return response()->json($forecast);
    }

    public function getConversionFunnel(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $funnel = $this->salesAnalytics->getConversionFunnel($fromDate, $toDate);

        return response()->json($funnel);
    }

    // ==================== Inventory Analytics ====================

    public function getInventorySummary(Request $request)
    {
        $warehouseId = $request->warehouse_id;

        $summary = $this->inventoryAnalytics->getInventorySummary($warehouseId);

        return response()->json($summary);
    }

    public function getInventoryTurnover(Request $request)
    {
        $days = $request->days ?? 30;
        $warehouseId = $request->warehouse_id;

        $turnover = $this->inventoryAnalytics->getInventoryTurnover($days, $warehouseId);

        return response()->json($turnover);
    }

    public function getSlowMovingInventory(Request $request)
    {
        $days = $request->days ?? 90;
        $warehouseId = $request->warehouse_id;

        $slowMoving = $this->inventoryAnalytics->getSlowMovingInventory($days, $warehouseId);

        return response()->json($slowMoving);
    }

    public function getStockoutAnalysis(Request $request)
    {
        $days = $request->days ?? 30;
        $warehouseId = $request->warehouse_id;

        $analysis = $this->inventoryAnalytics->getStockoutAnalysis($days, $warehouseId);

        return response()->json($analysis);
    }

    public function getInventoryValuation(Request $request)
    {
        $warehouseId = $request->warehouse_id;

        $valuation = $this->inventoryAnalytics->getInventoryValuation($warehouseId);

        return response()->json($valuation);
    }

    public function getABCAnalysis(Request $request)
    {
        $warehouseId = $request->warehouse_id;

        $abcAnalysis = $this->inventoryAnalytics->getABCAnalysis($warehouseId);

        return response()->json($abcAnalysis);
    }

    public function getInventoryHealthScore(Request $request)
    {
        $warehouseId = $request->warehouse_id;

        $healthScore = $this->inventoryAnalytics->getInventoryHealthScore($warehouseId);

        return response()->json($healthScore);
    }

    // ==================== Warehouse Analytics ====================

    public function getWarehousePerformance(Request $request)
    {
        $warehouseId = $request->warehouse_id;
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $performance = $this->warehouseAnalytics->getWarehousePerformance($warehouseId, $fromDate, $toDate);

        return response()->json($performance);
    }

    public function getBinUtilization(Request $request)
    {
        $warehouseId = $request->warehouse_id;

        $utilization = $this->warehouseAnalytics->getBinUtilization($warehouseId);

        return response()->json($utilization);
    }

    public function getCycleCountAccuracy(Request $request)
    {
        $warehouseId = $request->warehouse_id;
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $accuracy = $this->warehouseAnalytics->getCycleCountAccuracy($warehouseId, $fromDate, $toDate);

        return response()->json($accuracy);
    }

    public function getPickerPerformance(Request $request)
    {
        $warehouseId = $request->warehouse_id;
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $performance = $this->warehouseAnalytics->getPickerPerformance($warehouseId, $fromDate, $toDate);

        return response()->json($performance);
    }

    public function getCapacityPlanning(Request $request)
    {
        $warehouseId = $request->warehouse_id;

        $planning = $this->warehouseAnalytics->getCapacityPlanning($warehouseId);

        return response()->json($planning);
    }

    // ==================== Financial Analytics ====================

    public function getFinancialSummary(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subMonth()->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $summary = $this->financialAnalytics->getFinancialSummary($fromDate, $toDate);

        return response()->json($summary);
    }

    public function getRevenueByCategory(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subMonth()->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $byCategory = $this->financialAnalytics->getRevenueByCategory($fromDate, $toDate);

        return response()->json($byCategory);
    }

    public function getExpenseBreakdown(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subMonth()->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $breakdown = $this->financialAnalytics->getExpenseBreakdown($fromDate, $toDate);

        return response()->json($breakdown);
    }

    public function getCashFlowAnalysis(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subMonth()->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $cashFlow = $this->financialAnalytics->getCashFlowAnalysis($fromDate, $toDate);

        return response()->json($cashFlow);
    }

    public function getProfitAndLoss(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subMonth()->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $pnl = $this->financialAnalytics->getProfitAndLoss($fromDate, $toDate);

        return response()->json($pnl);
    }

    public function getAccountsAging(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();

        $aging = $this->financialAnalytics->getAccountsAging($asOfDate);

        return response()->json($aging);
    }

    public function getFinancialRatios(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();

        $ratios = $this->financialAnalytics->getFinancialRatios($asOfDate);

        return response()->json($ratios);
    }

    public function getBudgetVsActual(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subMonth()->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $comparison = $this->financialAnalytics->getBudgetVsActual($fromDate, $toDate);

        return response()->json($comparison);
    }

    // ==================== Metrics Management ====================

    public function indexMetrics(Request $request)
    {
        $query = AnalyticsMetric::query();

        if ($request->category) {
            $query->byCategory($request->category);
        }

        if ($request->active_only) {
            $query->active();
        }

        return response()->json($query->orderBy('sort_order')->get());
    }

    public function showMetric($id)
    {
        $metric = AnalyticsMetric::with(['dataPoints' => function ($query) {
            $query->orderBy('recorded_date')->limit(30);
        }])->findOrFail($id);

        return response()->json($metric);
    }

    public function storeMetric(Request $request)
    {
        $validated = $request->validate([
            'metric_key' => 'required|string|unique:analytics_metrics,metric_key',
            'name' => 'required|string',
            'name_ar' => 'nullable|string',
            'description' => 'nullable|string',
            'category' => 'required|in:sales,inventory,warehouse,financial,customer,operational',
            'data_type' => 'required|in:number,percentage,currency,count,duration',
            'aggregation' => 'required|in:sum,avg,count,min,max,last',
            'unit' => 'nullable|string',
            'calculation_config' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $metric = AnalyticsMetric::create($validated);

        return response()->json($metric, 201);
    }

    public function updateMetric(Request $request, $id)
    {
        $metric = AnalyticsMetric::findOrFail($id);

        $validated = $request->validate([
            'metric_key' => 'string|unique:analytics_metrics,metric_key,' . $id,
            'name' => 'string',
            'name_ar' => 'nullable|string',
            'description' => 'nullable|string',
            'category' => 'in:sales,inventory,warehouse,financial,customer,operational',
            'data_type' => 'in:number,percentage,currency,count,duration',
            'aggregation' => 'in:sum,avg,count,min,max,last',
            'unit' => 'nullable|string',
            'calculation_config' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $metric->update($validated);

        return response()->json($metric);
    }

    public function destroyMetric($id)
    {
        $metric = AnalyticsMetric::findOrFail($id);
        $metric->delete();

        return response()->json(['message' => 'Metric deleted successfully']);
    }

    public function getMetricData(Request $request, $id)
    {
        $metric = AnalyticsMetric::findOrFail($id);

        $query = AnalyticsDataPoint::where('metric_id', $id);

        if ($request->from_date) {
            $query->where('recorded_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->where('recorded_date', '<=', $request->to_date);
        }

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->channel_id) {
            $query->where('channel_id', $request->channel_id);
        }

        return response()->json($query->orderBy('recorded_date')->get());
    }

    // ==================== Reports Management ====================

    public function indexReports(Request $request)
    {
        $query = Report::with(['creator', 'widgets']);

        if ($request->type) {
            $query->byType($request->type);
        }

        if ($request->public_only) {
            $query->public();
        }

        return response()->json($query->paginate(20));
    }

    public function showReport($id)
    {
        $report = Report::with(['creator', 'widgets.dashboard'])->findOrFail($id);

        return response()->json($report);
    }

    public function storeReport(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'name_ar' => 'nullable|string',
            'description' => 'nullable|string',
            'type' => 'required|in:sales,inventory,warehouse,financial,customer,custom',
            'format' => 'required|in:table,chart,pivot,summary',
            'query_config' => 'required|array',
            'filter_config' => 'nullable|array',
            'column_config' => 'nullable|array',
            'chart_config' => 'nullable|array',
            'is_public' => 'boolean',
            'is_scheduled' => 'boolean',
            'schedule_frequency' => 'nullable|in:daily,weekly,monthly,quarterly',
            'schedule_config' => 'nullable|array',
        ]);

        $validated['created_by'] = $request->user()->id;

        $report = Report::create($validated);

        return response()->json($report, 201);
    }

    public function updateReport(Request $request, $id)
    {
        $report = Report::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string',
            'name_ar' => 'nullable|string',
            'description' => 'nullable|string',
            'type' => 'in:sales,inventory,warehouse,financial,customer,custom',
            'format' => 'in:table,chart,pivot,summary',
            'query_config' => 'array',
            'filter_config' => 'nullable|array',
            'column_config' => 'nullable|array',
            'chart_config' => 'nullable|array',
            'is_public' => 'boolean',
            'is_scheduled' => 'boolean',
            'schedule_frequency' => 'nullable|in:daily,weekly,monthly,quarterly',
            'schedule_config' => 'nullable|array',
        ]);

        $report->update($validated);

        return response()->json($report);
    }

    public function destroyReport($id)
    {
        $report = Report::findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Report deleted successfully']);
    }

    public function runReport($id)
    {
        $report = Report::findOrFail($id);
        $report->markAsRun();

        return response()->json(['message' => 'Report run successfully']);
    }

    // ==================== Dashboards Management ====================

    public function indexDashboards(Request $request)
    {
        $query = Dashboard::with(['creator', 'widgets.metric', 'widgets.report']);

        if ($request->type) {
            $query->byType($request->type);
        }

        if ($request->public_only) {
            $query->public();
        }

        return response()->json($query->paginate(20));
    }

    public function showDashboard($id)
    {
        $dashboard = Dashboard::with(['widgets.metric', 'widgets.report', 'widgets.metric.dataPoints' => function ($query) {
            $query->orderBy('recorded_date')->limit(30);
        }])->findOrFail($id);

        return response()->json($dashboard);
    }

    public function storeDashboard(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'name_ar' => 'nullable|string',
            'description' => 'nullable|string',
            'type' => 'required|in:executive,sales,inventory,warehouse,financial,custom',
            'layout_config' => 'nullable|array',
            'is_public' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $validated['created_by'] = $request->user()->id;

        $dashboard = Dashboard::create($validated);

        return response()->json($dashboard, 201);
    }

    public function updateDashboard(Request $request, $id)
    {
        $dashboard = Dashboard::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string',
            'name_ar' => 'nullable|string',
            'description' => 'nullable|string',
            'type' => 'in:executive,sales,inventory,warehouse,financial,custom',
            'layout_config' => 'nullable|array',
            'is_public' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $dashboard->update($validated);

        return response()->json($dashboard);
    }

    public function destroyDashboard($id)
    {
        $dashboard = Dashboard::findOrFail($id);
        $dashboard->delete();

        return response()->json(['message' => 'Dashboard deleted successfully']);
    }

    public function addWidget(Request $request, $dashboardId)
    {
        $dashboard = Dashboard::findOrFail($dashboardId);

        $validated = $request->validate([
            'metric_id' => 'nullable|exists:analytics_metrics,id',
            'report_id' => 'nullable|exists:reports,id',
            'widget_type' => 'required|in:number_card,chart,table,gauge,progress,list',
            'title' => 'required|string',
            'title_ar' => 'nullable|string',
            'config' => 'required|array',
            'position_x' => 'integer',
            'position_y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $validated['dashboard_id'] = $dashboardId;

        $widget = DashboardWidget::create($validated);

        return response()->json($widget, 201);
    }

    public function updateWidget(Request $request, $id)
    {
        $widget = DashboardWidget::findOrFail($id);

        $validated = $request->validate([
            'metric_id' => 'nullable|exists:analytics_metrics,id',
            'report_id' => 'nullable|exists:reports,id',
            'widget_type' => 'in:number_card,chart,table,gauge,progress,list',
            'title' => 'string',
            'title_ar' => 'nullable|string',
            'config' => 'array',
            'position_x' => 'integer',
            'position_y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $widget->update($validated);

        return response()->json($widget);
    }

    public function destroyWidget($id)
    {
        $widget = DashboardWidget::findOrFail($id);
        $widget->delete();

        return response()->json(['message' => 'Widget deleted successfully']);
    }

    // ==================== Site Visitors ====================

    /**
     * Headline visitor figures, each against the equal-length period before it
     * — same reading `getOverview()` gives the BI landing cards.
     */
    public function getVisitorsSummary(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $previous = PeriodComparison::previousWindow($fromDate, $toDate);

        $current = $this->visitorAnalytics->summary($fromDate, $toDate);
        $before = $this->visitorAnalytics->summary($previous['from'], $previous['to']);

        return response()->json([
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'previous_from' => $previous['from'],
                'previous_to' => $previous['to'],
            ],
            'total_visits' => PeriodComparison::compare($current['total_visits'], $before['total_visits']),
            'unique_visitors' => PeriodComparison::compare($current['unique_visitors'], $before['unique_visitors']),
            'bot_share' => PeriodComparison::compare($current['bot_share'], $before['bot_share']),
            'avg_daily_visits' => $current['avg_daily_visits'],
        ]);
    }

    public function getVisitorsTrend(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        return response()->json($this->visitorAnalytics->trend($fromDate, $toDate));
    }

    public function getVisitorsBreakdown(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        return response()->json($this->visitorAnalytics->breakdown($fromDate, $toDate));
    }

    public function getVisitorsTopPages(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();
        $limit = (int) ($request->limit ?? 10);

        return response()->json([
            'pages' => $this->visitorAnalytics->topPages($fromDate, $toDate, $limit),
            'referrers' => $this->visitorAnalytics->topReferrers($fromDate, $toDate, $limit),
        ]);
    }

    public function getVisitorsLog(Request $request)
    {
        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();
        $perPage = (int) ($request->per_page ?? 20);
        $page = (int) ($request->page ?? 1);

        $log = $this->visitorAnalytics->log(
            $fromDate,
            $toDate,
            $request->only(['search', 'device_type', 'browser', 'is_bot']),
            $perPage,
            $page
        );

        return response()->json($log);
    }

    public function getVisitorsFilters()
    {
        return response()->json([
            'device_types' => $this->visitorAnalytics->distinctDeviceTypes(),
            'browsers' => $this->visitorAnalytics->distinctBrowsers(),
        ]);
    }
}

