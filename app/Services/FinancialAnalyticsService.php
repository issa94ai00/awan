<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsDataPoint;
use Illuminate\Support\Collection;

class FinancialAnalyticsService
{
    /**
     * Get financial summary for a period
     */
    public function getFinancialSummary($fromDate, $toDate): array
    {
        $salesRevenue = $this->getSalesRevenue($fromDate, $toDate);
        $purchaseCosts = $this->getPurchaseCosts($fromDate, $toDate);
        $operatingExpenses = $this->getOperatingExpenses($fromDate, $toDate);
        $receivables = $this->getAccountsReceivable($toDate);
        $payables = $this->getAccountsPayable($toDate);

        $grossProfit = $salesRevenue - $purchaseCosts;
        $operatingProfit = $grossProfit - $operatingExpenses;

        return [
            'revenue' => $salesRevenue,
            'cost_of_goods_sold' => $purchaseCosts,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'operating_profit' => $operatingProfit,
            'gross_margin' => $salesRevenue > 0 ? ($grossProfit / $salesRevenue) * 100 : 0,
            'operating_margin' => $salesRevenue > 0 ? ($operatingProfit / $salesRevenue) * 100 : 0,
            'accounts_receivable' => $receivables,
            'accounts_payable' => $payables,
            'working_capital' => $receivables - $payables,
        ];
    }

    /**
     * Get sales revenue
     */
    protected function getSalesRevenue($fromDate, $toDate): float
    {
        return SalesOrder::whereBetween('order_date', [$fromDate, $toDate])
            ->where('status', SalesOrder::STATUS_DELIVERED)
            ->sum('total_amount');
    }

    /**
     * Get purchase costs
     */
    protected function getPurchaseCosts($fromDate, $toDate): float
    {
        return PurchaseOrder::whereBetween('order_date', [$fromDate, $toDate])
            ->where('status', 'received')
            ->sum('total_amount');
    }

    /**
     * Get operating expenses from journal entries
     */
    protected function getOperatingExpenses($fromDate, $toDate): float
    {
        return JournalEntry::whereBetween('entry_date', [$fromDate, $toDate])
            ->whereHas('lines', function ($query) {
                $query->where('debit', '>', 0);
            })
            ->with('lines')
            ->get()
            ->sum(function ($entry) {
                return $entry->lines->where('debit', '>', 0)->sum('debit');
            });
    }

    /**
     * Get accounts receivable
     */
    protected function getAccountsReceivable($toDate): float
    {
        return Invoice::where('due_date', '>=', $toDate)
            ->where('status', '!=', 'paid')
            ->sum('amount');
    }

    /**
     * Get accounts payable
     */
    protected function getAccountsPayable($toDate): float
    {
        return PurchaseOrder::where('due_date', '>=', $toDate)
            ->where('status', '!=', 'paid')
            ->sum('total_amount');
    }

    /**
     * Get revenue by category
     */
    public function getRevenueByCategory($fromDate, $toDate): Collection
    {
        $orders = SalesOrder::whereBetween('order_date', [$fromDate, $toDate])
            ->where('status', SalesOrder::STATUS_DELIVERED)
            ->with('items.product.category')
            ->get();

        return $orders->flatMap->items
            ->groupBy(function ($item) {
                return $item->product?->category?->name ?? 'Uncategorized';
            })
            ->map(function ($categoryItems) {
                return [
                    'category' => $categoryItems->first()->product?->category?->name ?? 'Uncategorized',
                    'revenue' => $categoryItems->sum('total_price'),
                    'quantity' => $categoryItems->sum('quantity'),
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * Get expense breakdown
     */
    public function getExpenseBreakdown($fromDate, $toDate): Collection
    {
        $entries = JournalEntry::whereBetween('entry_date', [$fromDate, $toDate])
            ->with(['lines.ledgerAccount'])
            ->get();

        return $entries->flatMap->lines
            ->where('debit', '>', 0)
            ->groupBy('ledger_account_id')
            ->map(function ($accountLines) {
                $account = $accountLines->first()->ledgerAccount;
                return [
                    'account_id' => $account?->id,
                    'account_name' => $account?->name ?? 'Unknown',
                    'account_code' => $account?->code,
                    'amount' => $accountLines->sum('debit'),
                ];
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * Get cash flow analysis
     */
    public function getCashFlowAnalysis($fromDate, $toDate): array
    {
        $cashInflows = JournalEntry::whereBetween('entry_date', [$fromDate, $toDate])
            ->whereHas('lines', function ($query) {
                $query->where('credit', '>', 0);
            })
            ->with('lines')
            ->get()
            ->sum(function ($entry) {
                return $entry->lines->where('credit', '>', 0)->sum('credit');
            });

        $cashOutflows = JournalEntry::whereBetween('entry_date', [$fromDate, $toDate])
            ->whereHas('lines', function ($query) {
                $query->where('debit', '>', 0);
            })
            ->with('lines')
            ->get()
            ->sum(function ($entry) {
                return $entry->lines->where('debit', '>', 0)->sum('debit');
            });

        $netCashFlow = $cashInflows - $cashOutflows;

        return [
            'cash_inflows' => $cashInflows,
            'cash_outflows' => $cashOutflows,
            'net_cash_flow' => $netCashFlow,
            'cash_flow_ratio' => $cashOutflows > 0 ? ($netCashFlow / $cashOutflows) * 100 : 0,
        ];
    }

    /**
     * Get profit and loss statement
     */
    public function getProfitAndLoss($fromDate, $toDate): array
    {
        $summary = $this->getFinancialSummary($fromDate, $toDate);

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'revenue' => [
                'total' => $summary['revenue'],
                'by_category' => $this->getRevenueByCategory($fromDate, $toDate)->toArray(),
            ],
            'cost_of_goods_sold' => $summary['cost_of_goods_sold'],
            'gross_profit' => $summary['gross_profit'],
            'gross_margin_percentage' => $summary['gross_margin'],
            'operating_expenses' => [
                'total' => $summary['operating_expenses'],
                'breakdown' => $this->getExpenseBreakdown($fromDate, $toDate)->toArray(),
            ],
            'operating_profit' => $summary['operating_profit'],
            'operating_margin_percentage' => $summary['operating_margin'],
            'net_profit' => $summary['operating_profit'], // Simplified, would include taxes/interest in full implementation
        ];
    }

    /**
     * Get accounts aging
     */
    public function getAccountsAging($asOfDate): array
    {
        $invoices = Invoice::where('due_date', '<=', $asOfDate)
            ->where('status', '!=', 'paid')
            ->get();

        $agingBuckets = [
            'current' => 0,
            '1_30_days' => 0,
            '31_60_days' => 0,
            '61_90_days' => 0,
            'over_90_days' => 0,
        ];

        foreach ($invoices as $invoice) {
            $daysOverdue = $invoice->due_date->diffInDays($asOfDate);
            
            if ($daysOverdue <= 0) {
                $agingBuckets['current'] += $invoice->amount;
            } elseif ($daysOverdue <= 30) {
                $agingBuckets['1_30_days'] += $invoice->amount;
            } elseif ($daysOverdue <= 60) {
                $agingBuckets['31_60_days'] += $invoice->amount;
            } elseif ($daysOverdue <= 90) {
                $agingBuckets['61_90_days'] += $invoice->amount;
            } else {
                $agingBuckets['over_90_days'] += $invoice->amount;
            }
        }

        return $agingBuckets;
    }

    /**
     * Get key financial ratios
     */
    public function getFinancialRatios($asOfDate): array
    {
        $summary = $this->getFinancialSummary(
            now()->subMonth()->toDateString(),
            $asOfDate
        );

        $currentAssets = $summary['accounts_receivable'];
        $currentLiabilities = $summary['accounts_payable'];

        return [
            'current_ratio' => $currentLiabilities > 0 ? $currentAssets / $currentLiabilities : 0,
            'quick_ratio' => $currentLiabilities > 0 ? $currentAssets / $currentLiabilities : 0,
            'working_capital_turnover' => $currentLiabilities > 0 ? $summary['revenue'] / $currentLiabilities : 0,
            'gross_profit_margin' => $summary['gross_margin'],
            'operating_profit_margin' => $summary['operating_margin'],
        ];
    }

    /**
     * Record financial metrics
     */
    public function recordMetrics($date = null): void
    {
        $date = $date ?? now()->toDateString();
        $fromDate = now()->subDay()->toDateString();

        $summary = $this->getFinancialSummary($fromDate, $date);

        // Revenue metric
        $this->recordMetric('daily_revenue', $summary['revenue'], $date);

        // Gross profit metric
        $this->recordMetric('daily_gross_profit', $summary['gross_profit'], $date);

        // Operating profit metric
        $this->recordMetric('daily_operating_profit', $summary['operating_profit'], $date);
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
     * Get budget vs actual comparison
     */
    public function getBudgetVsActual($fromDate, $toDate): array
    {
        $actualRevenue = $this->getSalesRevenue($fromDate, $toDate);
        $actualExpenses = $this->getOperatingExpenses($fromDate, $toDate);

        // In a full implementation, this would compare against budget data
        // For now, we'll use a simple projection based on previous period
        $budgetRevenue = $actualRevenue * 1.1; // Assume 10% growth target
        $budgetExpenses = $actualExpenses * 0.95; // Assume 5% cost reduction target

        return [
            'revenue' => [
                'budget' => $budgetRevenue,
                'actual' => $actualRevenue,
                'variance' => $actualRevenue - $budgetRevenue,
                'variance_percentage' => $budgetRevenue > 0 ? (($actualRevenue - $budgetRevenue) / $budgetRevenue) * 100 : 0,
            ],
            'expenses' => [
                'budget' => $budgetExpenses,
                'actual' => $actualExpenses,
                'variance' => $actualExpenses - $budgetExpenses,
                'variance_percentage' => $budgetExpenses > 0 ? (($actualExpenses - $budgetExpenses) / $budgetExpenses) * 100 : 0,
            ],
        ];
    }
}
