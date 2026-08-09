<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingReportController extends Controller
{
    /** Rounding tolerance when deciding whether a report balances. */
    private const EPSILON = 0.005;

    /**
     * Entry statuses that have not hit the books and must stay out of every
     * report. `reversed` is deliberately absent: reversing writes a second,
     * mirror-image entry, so both sides have to be counted for them to cancel —
     * dropping the original would leave the reversal standing on its own and
     * invert the amount.
     */
    private const UNPOSTED_STATUSES = ['draft', 'pending', 'void', 'cancelled'];

    /**
     * Trial balance for a period.
     *
     * Movements are aggregated in SQL rather than by loading every journal line
     * into memory, and the period is honoured — the previous version summed the
     * whole ledger regardless of the dates the user picked, and reused the
     * running `balance` column so the "balance" shown never matched the debit
     * and credit columns beside it.
     */
    public function trialBalance(Request $request): JsonResponse
    {
        [$fromDate, $toDate] = $this->period($request);

        $rows = DB::table('ledger_accounts as a')
            ->leftJoin('journal_entry_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entry_headers as h', function ($join) use ($fromDate, $toDate) {
                $join->on('h.id', '=', 'l.journal_entry_header_id')
                    ->whereNull('h.deleted_at')
                    ->whereBetween('h.entry_date', [$fromDate, $toDate]);
            })
            ->selectRaw('a.id, a.code, a.name, a.type, a.posting_role,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.debit END), 0) as debits,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.credit END), 0) as credits')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.posting_role')
            ->orderBy('a.code')
            ->get();

        $accounts = $rows->map(function ($row) {
            $debits = (float) $row->debits;
            $credits = (float) $row->credits;

            return [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'debits' => round($debits, 2),
                'credits' => round($credits, 2),
                // Closing movement for the period, on the account's normal side.
                'balance' => round(LedgerAccount::signedDelta($row->type, $debits, $credits), 2),
            ];
        });

        $totalDebits = round($accounts->sum('debits'), 2);
        $totalCredits = round($accounts->sum('credits'), 2);

        return response()->json([
            'success' => true,
            'message' => 'Trial balance retrieved successfully',
            'data' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                // Accounts with no movement in the period only add noise.
                'accounts' => $accounts->filter(fn ($a) => $a['debits'] != 0.0 || $a['credits'] != 0.0)->values(),
                'all_accounts' => $accounts,
                'totals' => ['debits' => $totalDebits, 'credits' => $totalCredits],
                'difference' => round($totalDebits - $totalCredits, 2),
                'is_balanced' => abs($totalDebits - $totalCredits) < self::EPSILON,
                // Surfaced so a corrupt entry is visible here instead of quietly
                // skewing every downstream statement.
                'unbalanced_entries' => $this->unbalancedEntries($fromDate, $toDate),
            ],
        ]);
    }

    /**
     * Accounts whose movement is a deduction from revenue rather than revenue.
     * They are credit-normal by type but debit-normal in reality, so they must
     * be shown as deductions instead of being buried in the revenue list as
     * negative numbers.
     */
    private const CONTRA_REVENUE_ROLES = ['sales_returns', 'sales_discounts'];

    /** Accounts that make up the cost of what was sold, above the gross margin line. */
    private const COST_OF_SALES_ROLES = ['cogs'];

    /**
     * Income statement for a period.
     *
     * Reported in the standard stepped form rather than as two flat lists:
     *
     *     gross revenue − returns and discounts   = net revenue
     *     net revenue   − cost of goods sold      = gross profit
     *     gross profit  − operating expenses      = net income
     *
     * The previous version summed every revenue account into one figure and
     * every expense account into another, which put cost of goods sold in the
     * same bucket as salaries and rent. That makes the gross margin — the single
     * number that says whether the trading itself is profitable — impossible to
     * read off the statement, and it is the reason the split matters here now
     * that sales actually post their cost.
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        [$fromDate, $toDate] = $this->period($request);

        $current = $this->buildIncomeStatement($fromDate, $toDate);

        // The same span immediately before this one, so a figure can be read as
        // better or worse rather than just large or small.
        [$prevFrom, $prevTo] = $this->precedingPeriod($fromDate, $toDate);
        $previous = $this->buildIncomeStatement($prevFrom, $prevTo);

        return response()->json([
            'success' => true,
            'message' => 'Income statement retrieved successfully',
            'data' => $current + [
                // Kept flat alongside the stepped figures: the previous shape of
                // this endpoint is what the screen already binds to.
                'comparison' => [
                    'period' => ['from' => $prevFrom, 'to' => $prevTo],
                    'net_revenue' => $previous['net_revenue'],
                    'gross_profit' => $previous['gross_profit'],
                    'operating_expenses' => $previous['operating_expenses']['total'],
                    'net_income' => $previous['net_income'],
                ],
                // An entry whose own lines do not add up skews every figure
                // above, so it is reported with the statement rather than being
                // discoverable only on the trial balance.
                'unbalanced_entries' => $this->unbalancedEntries($fromDate, $toDate),
                'warnings' => $this->incomeStatementWarnings($current),
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function buildIncomeStatement(string $fromDate, string $toDate): array
    {
        $rows = $this->movementsByType(['revenue', 'expense'], $fromDate, $toDate);

        // A parent heading such as "4000 الإيرادات" carries no postings of its
        // own; listing it, and every untouched account, buried the handful of
        // lines that actually moved.
        $moved = fn ($r) => abs((float) $r->debits) > self::EPSILON || abs((float) $r->credits) > self::EPSILON;

        $line = fn ($r, float $amount) => [
            'id' => $r->id,
            'code' => $r->code,
            'name' => $r->name,
            'posting_role' => $r->posting_role,
            'amount' => round($amount, 2),
        ];

        $revenueRows = $rows->where('type', 'revenue')->filter($moved);
        $expenseRows = $rows->where('type', 'expense')->filter($moved);

        // Contra-revenue is debit-normal, so its deduction is debits − credits.
        $contraRevenue = $revenueRows
            ->filter(fn ($r) => in_array($r->posting_role, self::CONTRA_REVENUE_ROLES, true))
            ->map(fn ($r) => $line($r, (float) $r->debits - (float) $r->credits))
            ->values();

        $grossRevenue = $revenueRows
            ->reject(fn ($r) => in_array($r->posting_role, self::CONTRA_REVENUE_ROLES, true))
            ->map(fn ($r) => $line($r, (float) $r->credits - (float) $r->debits))
            ->values();

        $costOfSales = $expenseRows
            ->filter(fn ($r) => in_array($r->posting_role, self::COST_OF_SALES_ROLES, true))
            ->map(fn ($r) => $line($r, (float) $r->debits - (float) $r->credits))
            ->values();

        $operatingExpenses = $expenseRows
            ->reject(fn ($r) => in_array($r->posting_role, self::COST_OF_SALES_ROLES, true))
            ->map(fn ($r) => $line($r, (float) $r->debits - (float) $r->credits))
            ->values();

        $grossRevenueTotal = round($grossRevenue->sum('amount'), 2);
        $contraTotal = round($contraRevenue->sum('amount'), 2);
        $netRevenue = round($grossRevenueTotal - $contraTotal, 2);
        $costOfSalesTotal = round($costOfSales->sum('amount'), 2);
        $grossProfit = round($netRevenue - $costOfSalesTotal, 2);
        $operatingTotal = round($operatingExpenses->sum('amount'), 2);
        $netIncome = round($grossProfit - $operatingTotal, 2);

        return [
            'period' => ['from' => $fromDate, 'to' => $toDate],

            'gross_revenue' => ['total' => $grossRevenueTotal, 'accounts' => $grossRevenue],
            'contra_revenue' => ['total' => $contraTotal, 'accounts' => $contraRevenue],
            'net_revenue' => $netRevenue,

            'cost_of_sales' => ['total' => $costOfSalesTotal, 'accounts' => $costOfSales],
            'gross_profit' => $grossProfit,
            'gross_margin_pct' => $this->marginPct($grossProfit, $netRevenue),

            'operating_expenses' => ['total' => $operatingTotal, 'accounts' => $operatingExpenses],
            'net_income' => $netIncome,
            'net_margin_pct' => $this->marginPct($netIncome, $netRevenue),

            // The original two-bucket shape, so existing callers keep working.
            // `revenue.total` is net of returns and discounts, which is what the
            // net income figure has always been computed from.
            'revenue' => [
                'total' => $netRevenue,
                // Deductions carry their old sign here: negative inside the flat
                // revenue list is what nets them off its total.
                'accounts' => $grossRevenue->concat(
                    $contraRevenue->map(fn ($a) => array_merge($a, ['amount' => round(-$a['amount'], 2)]))
                )->values(),
            ],
            'expenses' => [
                'total' => round($costOfSalesTotal + $operatingTotal, 2),
                'accounts' => $costOfSales->concat($operatingExpenses)->values(),
            ],
        ];
    }

    /**
     * Margin as a percentage of net revenue. Undefined rather than zero when
     * there is no revenue — a period with costs and no sales has no margin, and
     * reporting 0% would read as breaking even.
     */
    private function marginPct(float $amount, float $netRevenue): ?float
    {
        if (abs($netRevenue) < self::EPSILON) {
            return null;
        }

        return round(($amount / $netRevenue) * 100, 1);
    }

    /**
     * Conditions that make the statement misleading even though every figure on
     * it is arithmetically right. Surfaced rather than silently rendered.
     *
     * @param  array<string,mixed>  $statement
     * @return array<int,array{level:string,message:string}>
     */
    private function incomeStatementWarnings(array $statement): array
    {
        $warnings = [];

        // Cost booked with no sale behind it: the goods left but the invoice was
        // never raised or never reached the ledger, so the period shows a loss
        // that the business did not actually make.
        if ($statement['cost_of_sales']['total'] > self::EPSILON && abs($statement['net_revenue']) < self::EPSILON) {
            $warnings[] = [
                'level' => 'error',
                'message' => 'سُجّلت تكلفة بضاعة مباعة دون أي إيراد مقابل في الفترة — تحقق من أن فواتير المبيعات مُرحَّلة إلى دفتر الأستاذ.',
            ];
        }

        if ($statement['net_revenue'] > self::EPSILON && $statement['cost_of_sales']['total'] < self::EPSILON) {
            $warnings[] = [
                'level' => 'warning',
                'message' => 'لا توجد تكلفة بضاعة مباعة مقابل إيرادات الفترة — مجمل الربح مبالغ فيه. تأكد من تسعير تكلفة المنتجات ومن ترحيل قيود الشحن.',
            ];
        }

        if ($statement['contra_revenue']['total'] < -self::EPSILON) {
            $warnings[] = [
                'level' => 'warning',
                'message' => 'رصيد حسابات المردودات والخصومات دائن — وهو عكس طبيعتها، ما يشير إلى قيد مُدخل بالاتجاه الخاطئ.',
            ];
        }

        return $warnings;
    }

    /**
     * The period of equal length ending the day before this one starts, so a
     * month is compared with a month and a quarter with a quarter.
     *
     * @return array{0:string,1:string}
     */
    private function precedingPeriod(string $fromDate, string $toDate): array
    {
        $from = \Carbon\Carbon::parse($fromDate);
        $to = \Carbon\Carbon::parse($toDate);

        // Inclusive day count, so a single-day period compares against the day before.
        $days = $from->diffInDays($to) + 1;

        return [
            $from->copy()->subDays($days)->toDateString(),
            $from->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * Balance sheet as at a date.
     *
     * The previous version listed asset, liability and equity balances and then
     * checked A = L + E — which could never be true, because the period's
     * profit lives in the revenue and expense accounts until it is closed out.
     * The result is now carried into equity as "current period result", exactly
     * as a closing entry would, so the statement balances whenever the
     * underlying journal does.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $asOf = $request->input('as_of', now()->toDateString());
        $from = '1900-01-01';

        $rows = $this->movementsByType(['asset', 'liability', 'equity', 'revenue', 'expense'], $from, $asOf);

        $balanceOf = fn ($row) => round(
            LedgerAccount::signedDelta($row->type, (float) $row->debits, (float) $row->credits),
            2
        );

        $group = fn (string $type) => $rows->where('type', $type)
            ->map(fn ($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'balance' => $balanceOf($r),
            ])
            ->filter(fn ($a) => abs($a['balance']) > self::EPSILON)
            ->values();

        $assets = $group('asset');
        $liabilities = $group('liability');
        $equity = $group('equity');

        $totalRevenue = round($rows->where('type', 'revenue')->sum(fn ($r) => (float) $r->credits - (float) $r->debits), 2);
        $totalExpense = round($rows->where('type', 'expense')->sum(fn ($r) => (float) $r->debits - (float) $r->credits), 2);
        $currentResult = round($totalRevenue - $totalExpense, 2);

        $totalAssets = round($assets->sum('balance'), 2);
        $totalLiabilities = round($liabilities->sum('balance'), 2);
        $totalEquityPosted = round($equity->sum('balance'), 2);
        $totalEquity = round($totalEquityPosted + $currentResult, 2);

        return response()->json([
            'success' => true,
            'message' => 'Balance sheet retrieved successfully',
            'data' => [
                'as_of' => $asOf,
                'assets' => ['total' => $totalAssets, 'accounts' => $assets],
                'liabilities' => ['total' => $totalLiabilities, 'accounts' => $liabilities],
                'equity' => [
                    'total' => $totalEquity,
                    'accounts' => $equity,
                    'posted_total' => $totalEquityPosted,
                    // Unclosed profit for the period, shown as its own equity line.
                    'current_period_result' => $currentResult,
                ],
                'totals' => [
                    'assets' => $totalAssets,
                    'liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
                ],
                'difference' => round($totalAssets - ($totalLiabilities + $totalEquity), 2),
                'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < self::EPSILON,
            ],
        ]);
    }

    /**
     * Whether the books and the operational records still agree with each other.
     *
     * Each module could already answer for itself — an order's detail screen
     * diagnoses that order, the income statement warns about its own period —
     * but nothing asked the question across the system. So a single storefront
     * order whose invoice never reached the ledger was invisible until someone
     * happened to open it, and the only way to find them all was a CLI command.
     *
     * Every check here is a join between two records that must match. None of
     * them writes anything; the repairs stay deliberate.
     */
    public function systemHealth(): JsonResponse
    {
        $checks = [];

        /* ---- Documents that never reached the ledger ---- */

        $unpostedInvoices = DB::table('invoices')
            ->whereNotIn('status', ['cancelled'])
            ->where('total', '>', 0)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entry_headers')
                ->whereColumn('journal_entry_headers.posting_key', DB::raw("CONCAT('invoice:', invoices.id)"))
                ->whereNull('journal_entry_headers.deleted_at'))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as amount')
            ->first();

        $checks[] = $this->healthCheck(
            'unposted_invoices',
            'فواتير غير مُرحَّلة إلى دفتر الأستاذ',
            (int) $unpostedInvoices->n,
            'إيراد بقيمة ' . number_format((float) $unpostedInvoices->amount, 2) . ' لا يظهر في قائمة الدخل رغم إصدار فاتورته.',
            'شغّل: php artisan accounting:backfill --type=invoices --apply'
        );

        $unpostedPayments = DB::table('payments')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entry_headers')
                ->whereColumn('journal_entry_headers.posting_key', DB::raw("CONCAT('payment:', payments.id)"))
                ->whereNull('journal_entry_headers.deleted_at'))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $checks[] = $this->healthCheck(
            'unposted_payments',
            'دفعات غير مُرحَّلة',
            (int) $unpostedPayments->n,
            'تحصيل بقيمة ' . number_format((float) $unpostedPayments->amount, 2) . ' لم يصل الصندوق ولم يُخفِّض ذمم العملاء في الدفاتر.',
            'شغّل: php artisan accounting:backfill --type=payments --apply'
        );

        /* ---- Sales orders whose documents do not follow them ---- */

        $shippedWithoutCogs = DB::table('sales_orders')
            ->whereIn('status', ['shipped', 'delivered'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entry_headers')
                ->whereColumn('journal_entry_headers.posting_key', DB::raw("CONCAT('so_cogs:', sales_orders.id)"))
                ->whereNull('journal_entry_headers.deleted_at'))
            ->count();

        $checks[] = $this->healthCheck(
            'shipped_without_cogs',
            'طلبات شُحنت بلا قيد تكلفة',
            $shippedWithoutCogs,
            'خرجت بضاعتها من المستودع دون تسجيل تكلفتها، فمجمل الربح أعلى من حقيقته.',
            'راجع تسعير تكلفة المنتجات ثم أعد ترحيل قيود التكلفة.'
        );

        $confirmedWithoutInvoice = DB::table('sales_orders')
            ->whereNotIn('status', ['pending', 'cancelled'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('invoices')
                ->whereColumn('invoices.sales_order_id', 'sales_orders.id')
                ->where('invoices.status', '!=', 'cancelled'))
            ->count();

        $checks[] = $this->healthCheck(
            'confirmed_without_invoice',
            'طلبات مؤكدة بلا فاتورة',
            $confirmedWithoutInvoice,
            'الطلب مؤكد لكن لا توجد فاتورة، فالإيراد غير مثبت والعميل غير مدين به.',
            'افتح الطلب وأعد تأكيده لإنشاء الفاتورة.'
        );

        /* ---- Data that makes the costing wrong ---- */

        $unpricedStocked = DB::table('warehouse_inventory')
            ->join('products', 'products.id', '=', 'warehouse_inventory.product_id')
            ->where('warehouse_inventory.quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('products.cost_price')->orWhere('products.cost_price', '<=', 0))
            ->distinct()
            ->count('products.id');

        $checks[] = $this->healthCheck(
            'products_without_cost',
            'أصناف مخزَّنة بلا سعر تكلفة',
            $unpricedStocked,
            'تُحتسب تكلفة بيعها صفراً، فيظهر هامش الربح أعلى من حقيقته وقيمة المخزون أقل.',
            'أدخل سعر تكلفة هذه الأصناف من شاشة المخزون.'
        );

        $negativeStock = DB::table('warehouse_inventory')->where('quantity', '<', 0)->count();

        $checks[] = $this->healthCheck(
            'negative_stock',
            'أرصدة مخزون سالبة',
            $negativeStock,
            'كمية أقل من صفر تعني أن إخراجاً تم دون إدخال يقابله.',
            'سجّل تسوية مخزنية من شاشة المخزون.'
        );

        /* ---- The ledger's own integrity ---- */

        $unbalanced = count($this->unbalancedEntries('1900-01-01', now()->toDateString()));

        $checks[] = $this->healthCheck(
            'unbalanced_entries',
            'قيود غير متوازنة',
            $unbalanced,
            'قيد لا تتساوى فيه المدينية والدائنية يُفسد كل تقرير مبني عليه.',
            'راجع القيود من شاشة اليومية.'
        );

        $totals = DB::table('journal_entry_lines')->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
        $drift = round((float) $totals->d - (float) $totals->c, 2);

        $checks[] = $this->healthCheck(
            'ledger_drift',
            'اختلال إجمالي دفتر الأستاذ',
            abs($drift) > self::EPSILON ? 1 : 0,
            'فرق ' . number_format($drift, 2) . ' بين إجمالي المدين والدائن على مستوى الدفتر كله.',
            'ابدأ من القيود غير المتوازنة أعلاه.'
        );

        $failing = array_values(array_filter($checks, fn ($c) => $c['count'] > 0));

        return response()->json([
            'success' => true,
            'data' => [
                'checked_at' => now()->toDateTimeString(),
                'is_healthy' => $failing === [],
                'issue_count' => count($failing),
                'affected_records' => array_sum(array_column($failing, 'count')),
                'checks' => $checks,
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function healthCheck(string $code, string $title, int $count, string $detail, string $action): array
    {
        return [
            'code' => $code,
            'title' => $title,
            'count' => $count,
            'ok' => $count === 0,
            // Only describe the problem when there is one; a passing check that
            // still explains a failure reads as a warning.
            'detail' => $count > 0 ? $detail : null,
            'action' => $count > 0 ? $action : null,
        ];
    }

    /* ------------------------------------------------------------------ */

    /** @return \Illuminate\Support\Collection<int,object> */
    private function movementsByType(array $types, string $fromDate, string $toDate)
    {
        return DB::table('ledger_accounts as a')
            ->leftJoin('journal_entry_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entry_headers as h', function ($join) use ($fromDate, $toDate) {
                $join->on('h.id', '=', 'l.journal_entry_header_id')
                    ->whereNull('h.deleted_at')
                    ->whereNotIn('h.status', self::UNPOSTED_STATUSES)
                    ->whereBetween('h.entry_date', [$fromDate, $toDate]);
            })
            ->whereIn('a.type', $types)
            ->selectRaw('a.id, a.code, a.name, a.type, a.posting_role,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.debit END), 0) as debits,
                         COALESCE(SUM(CASE WHEN h.id IS NULL THEN 0 ELSE l.credit END), 0) as credits')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.posting_role')
            ->orderBy('a.code')
            ->get();
    }

    /** Entries whose own lines do not add up — these break every report. */
    private function unbalancedEntries(string $fromDate, string $toDate): array
    {
        return JournalEntryHeader::query()
            ->whereBetween('entry_date', [$fromDate, $toDate])
            ->whereHas('lines')
            ->withSum('lines as sum_debit', 'debit')
            ->withSum('lines as sum_credit', 'credit')
            ->get()
            ->filter(fn ($h) => abs((float) $h->sum_debit - (float) $h->sum_credit) > self::EPSILON)
            ->map(fn ($h) => [
                'id' => $h->id,
                'entry_number' => $h->entry_number,
                'entry_date' => optional($h->entry_date)->toDateString(),
                'debit' => round((float) $h->sum_debit, 2),
                'credit' => round((float) $h->sum_credit, 2),
                'difference' => round((float) $h->sum_debit - (float) $h->sum_credit, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * The reporting window.
     *
     * The dates were previously passed into the query exactly as they arrived.
     * A malformed value reached MySQL as a date literal and blew up the request,
     * and a reversed range (from after to) silently produced an empty report
     * that looked like a period with no activity rather than a bad filter.
     *
     * @return array{0:string,1:string}
     */
    private function period(Request $request): array
    {
        // Validated by hand against the exact shape the date pickers send.
        // Carbon::parse emits a PHP warning on its way to throwing, and
        // createFromFormat throws rather than returning false, so both would
        // turn a stray query string into log noise or a 500.
        $parse = function ($value, \Carbon\Carbon $fallback): \Carbon\Carbon {
            if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
                return $fallback;
            }

            [, $year, $month, $day] = $m;

            // Rejects 2025-13-45 as well as 2025-02-30, which would otherwise
            // roll silently into the next month and shift the whole period.
            return checkdate((int) $month, (int) $day, (int) $year)
                ? \Carbon\Carbon::create((int) $year, (int) $month, (int) $day)->startOfDay()
                : $fallback;
        };

        $from = $parse($request->input('date_from'), now()->startOfYear());
        $to = $parse($request->input('date_to'), now());

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->toDateString(), $to->toDateString()];
    }
}
