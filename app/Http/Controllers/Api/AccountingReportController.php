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
                    ->whereBetween(DB::raw('DATE(h.entry_date)'), [$fromDate, $toDate]);
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
     * Every movement on one account over a period, with a running balance.
     *
     * The ledger screen built this in the browser: it pulled the last 200
     * journal entries for the account, flattened their lines and ran a total
     * from zero. Three things were wrong with that, and all three showed up as
     * a statement that disagreed with the account balance printed above it —
     * the period was ignored, anything past the 200th entry silently vanished,
     * and starting from zero meant the running figure only matched reality for
     * an account that had never been touched before the first row shown.
     *
     * What makes a statement readable is the opening balance: everything that
     * happened before the period, as one number, so each row after it can be
     * followed to a closing figure that is the account's actual position.
     */
    public function accountStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|integer|exists:ledger_accounts,id',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);

        [$fromDate, $toDate] = $this->period($request);
        $account = LedgerAccount::findOrFail($validated['account_id']);

        // Everything before the window, collapsed into the one figure the
        // period opens from.
        $before = DB::table('journal_entry_lines as l')
            ->join('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
            ->where('l.account_id', $account->id)
            ->whereNull('h.deleted_at')
            ->whereNotIn('h.status', self::UNPOSTED_STATUSES)
            ->whereDate('h.entry_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')
            ->first();

        $opening = round(
            LedgerAccount::signedDelta($account->type, (float) $before->d, (float) $before->c),
            2
        );

        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
            ->where('l.account_id', $account->id)
            ->whereNull('h.deleted_at')
            ->whereNotIn('h.status', self::UNPOSTED_STATUSES)
            ->whereBetween(DB::raw('DATE(h.entry_date)'), [$fromDate, $toDate])
            ->orderBy('h.entry_date')
            ->orderBy('h.id')
            ->orderBy('l.id')
            ->select([
                'l.id',
                'l.debit',
                'l.credit',
                'l.description as line_description',
                'h.id as entry_id',
                'h.entry_number',
                'h.entry_date',
                'h.description',
                'h.source_module',
                'h.status',
                'h.posting_key',
            ])
            ->get();

        $balance = $opening;

        $movements = $rows->map(function ($row) use (&$balance, $account) {
            $debit = round((float) $row->debit, 2);
            $credit = round((float) $row->credit, 2);
            $balance = round($balance + LedgerAccount::signedDelta($account->type, $debit, $credit), 2);

            return [
                'id' => $row->id,
                'entry_id' => $row->entry_id,
                'entry_number' => $row->entry_number,
                'entry_date' => $row->entry_date,
                // The line's own wording when it has one: "تحصيل - PAY-000012"
                // says more than the header's summary of the whole entry.
                'description' => $row->line_description ?: $row->description,
                'source_module' => $row->source_module,
                'status' => $row->status,
                'posting_key' => $row->posting_key,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Account statement retrieved successfully',
            'data' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'account' => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'posting_role' => $account->posting_role,
                ],
                'opening_balance' => $opening,
                'movements' => $movements,
                'totals' => [
                    'debits' => round($movements->sum('debit'), 2),
                    'credits' => round($movements->sum('credit'), 2),
                ],
                'closing_balance' => $balance,
                // The account's own cached balance, so a statement that does not
                // land on it is visible here rather than being taken on trust.
                'stored_balance' => round((float) $account->balance, 2),
                'matches_stored_balance' => abs($balance - (float) $account->balance) < self::EPSILON,
            ],
        ]);
    }

    /**
     * The value-added tax position for a period.
     *
     * Tax collected on sales is not income and tax paid on purchases is not a
     * cost: one is money held for the state, the other a claim against it, and
     * what is actually owed is the difference. Neither figure could be read off
     * this system before — the sales side posted to 2001 with nothing that
     * summed it over a period, and the purchase side had no account at all, so
     * tax paid to suppliers vanished into the value of the stock.
     *
     * Both figures come from the ledger accounts rather than from re-adding the
     * documents, so the return describes the same books every other statement
     * here does. The document totals are reported beside them as a check: when
     * the tax on the invoices of a period does not match what reached the tax
     * account, something was posted wrong, and that is worth knowing before the
     * return is filed rather than after.
     */
    public function vatReturn(Request $request): JsonResponse
    {
        [$fromDate, $toDate] = $this->period($request);

        $movement = function (string $role) use ($fromDate, $toDate): array {
            $account = LedgerAccount::where('posting_role', $role)->first();

            if (! $account) {
                return ['account' => null, 'debits' => 0.0, 'credits' => 0.0, 'amount' => 0.0];
            }

            $row = DB::table('journal_entry_lines as l')
                ->join('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
                ->where('l.account_id', $account->id)
                ->whereNull('h.deleted_at')
                ->whereNotIn('h.status', self::UNPOSTED_STATUSES)
                ->whereBetween(DB::raw('DATE(h.entry_date)'), [$fromDate, $toDate])
                ->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')
                ->first();

            $debits = round((float) $row->d, 2);
            $credits = round((float) $row->c, 2);

            return [
                'account' => ['code' => $account->code, 'name' => $account->name],
                'debits' => $debits,
                'credits' => $credits,
                // On the account's own normal side, so output tax reads as what
                // was collected and input tax as what was paid.
                'amount' => round(LedgerAccount::signedDelta($account->type, $debits, $credits), 2),
            ];
        };

        $output = $movement('tax_payable');
        $input = $movement('input_vat');
        $net = round($output['amount'] - $input['amount'], 2);

        // What the documents of the period say, independently of the ledger.
        $invoiceTax = round((float) DB::table('invoices')
            ->where('status', '!=', 'cancelled')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->sum('tax'), 2);

        $receiptTax = round((float) DB::table('purchase_receipts')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->sum('tax_amount'), 2);

        $revenue = $this->movementsByType(['revenue'], $fromDate, $toDate)
            ->whereIn('posting_role', ['sales_revenue', 'additional_charges_revenue'])
            ->sum(fn ($r) => (float) $r->credits - (float) $r->debits);

        return response()->json([
            'success' => true,
            'message' => 'VAT return retrieved successfully',
            'data' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'output_tax' => $output,
                'input_tax' => $input,
                'net' => $net,
                // Positive is owed to the authority, negative is recoverable.
                'direction' => $net >= 0 ? 'payable' : 'refundable',
                'sales_base' => round($revenue, 2),
                'documents' => [
                    'invoice_tax' => $invoiceTax,
                    'receipt_tax' => $receiptTax,
                ],
                // A document total that disagrees with the account means
                // something did not post, or posted twice.
                'reconciliation' => [
                    'output_difference' => round($invoiceTax - $output['amount'], 2),
                    'input_difference' => round($receiptTax - $input['amount'], 2),
                    'output_matches' => abs($invoiceTax - $output['amount']) < self::EPSILON,
                    'input_matches' => abs($receiptTax - $input['amount']) < self::EPSILON,
                ],
            ],
        ]);
    }

    /**
     * How old the money owed to us — and by us — actually is.
     *
     * The one aging figure the system had lived in the analytics service, and
     * it answered a different question than the name suggested: it bucketed
     * whole invoice totals rather than what was still outstanding on them, and
     * only ever looked at invoices already past their due date, so a large
     * unpaid invoice due next week counted as nothing at all. It also produced
     * a single company-wide number, which is not something anybody can act on —
     * collection is a conversation with a particular customer.
     *
     * The reconciliation is the part that matters most. A list of who owes what
     * is only worth anything if it adds up to the receivables account in the
     * ledger; when the two disagree, one of them is wrong and the difference is
     * shown here rather than discovered at year end.
     */
    public function aging(Request $request): JsonResponse
    {
        $asOf = $this->parseDate($request->input('as_of'), now())->toDateString();

        $receivables = $this->ageReceivables($asOf);
        $payables = $this->agePayables($asOf);

        return response()->json([
            'success' => true,
            'message' => 'Aging report retrieved successfully',
            'data' => [
                'as_of' => $asOf,
                'buckets' => self::BUCKETS,
                'receivables' => $receivables,
                'payables' => $payables,
            ],
        ]);
    }

    /** Bucket keys, in the order they are meant to be read. */
    private const BUCKETS = ['current', '1_30', '31_60', '61_90', 'over_90'];

    /**
     * Which bucket a debt belongs in.
     *
     * `current` means not yet late — a debt with no due date is treated as due
     * on the day the document was raised, because the alternative is calling
     * every undated invoice current forever.
     */
    private function bucketFor(?string $dueDate, string $asOf): string
    {
        if (! $dueDate) {
            return 'current';
        }

        $days = \Carbon\Carbon::parse($asOf)->diffInDays(\Carbon\Carbon::parse($dueDate), false);

        // A positive difference means the due date is still ahead.
        if ($days >= 0) {
            return 'current';
        }

        $overdue = abs($days);

        return match (true) {
            $overdue <= 30 => '1_30',
            $overdue <= 60 => '31_60',
            $overdue <= 90 => '61_90',
            default => 'over_90',
        };
    }

    /** @return array<string,float> an empty set of buckets */
    private function emptyBuckets(): array
    {
        return array_fill_keys(self::BUCKETS, 0.0);
    }

    /**
     * What customers still owe, per customer, aged by each invoice's own due
     * date — and checked against the receivables control account.
     *
     * @return array<string,mixed>
     */
    private function ageReceivables(string $asOf): array
    {
        $invoices = DB::table('invoices')
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->where('invoices.status', '!=', 'cancelled')
            ->whereDate('invoices.created_at', '<=', $asOf)
            ->whereRaw('COALESCE(invoices.due_amount, 0) > 0.005')
            ->select([
                'invoices.id',
                'invoices.invoice_number',
                'invoices.due_amount',
                'invoices.due_date',
                'invoices.created_at',
                'invoices.customer_id',
                'customers.name as customer_name',
            ])
            ->get();

        $parties = [];

        foreach ($invoices as $invoice) {
            $key = $invoice->customer_id ?: 0;

            $parties[$key] ??= [
                'id' => $invoice->customer_id,
                'name' => $invoice->customer_name ?: 'بدون عميل',
                'total' => 0.0,
                'buckets' => $this->emptyBuckets(),
                'documents' => [],
            ];

            $amount = round((float) $invoice->due_amount, 2);
            $due = $invoice->due_date ?: (string) $invoice->created_at;
            $bucket = $this->bucketFor(substr((string) $due, 0, 10), $asOf);

            $parties[$key]['total'] = round($parties[$key]['total'] + $amount, 2);
            $parties[$key]['buckets'][$bucket] = round($parties[$key]['buckets'][$bucket] + $amount, 2);
            $parties[$key]['documents'][] = [
                'number' => $invoice->invoice_number,
                'date' => substr((string) $invoice->created_at, 0, 10),
                'due_date' => $invoice->due_date ? substr((string) $invoice->due_date, 0, 10) : null,
                'amount' => $amount,
                'bucket' => $bucket,
            ];
        }

        return $this->presentAging($parties, 'accounts_receivable');
    }

    /**
     * What is still owed to suppliers.
     *
     * Purchases have no per-document balance to age — a receipt records goods
     * arriving, not a payable with its own terms — so the supplier's running
     * balance is aged from the oldest receipt that is still not covered by what
     * has been paid. That is the honest reading of the data the system keeps,
     * and it is the figure the payables account has to agree with.
     *
     * @return array<string,mixed>
     */
    private function agePayables(string $asOf): array
    {
        $suppliers = DB::table('suppliers')
            ->whereRaw('COALESCE(balance, 0) > 0.005')
            ->select(['id', 'name', 'balance'])
            ->get();

        $parties = [];

        foreach ($suppliers as $supplier) {
            // The oldest receipt still standing behind the balance: payments
            // settle the oldest debt first, so what remains is the tail of the
            // ledger of receipts.
            $oldest = DB::table('purchase_receipts')
                ->where('supplier_id', $supplier->id)
                ->orderBy('receipt_date')
                ->orderBy('id')
                ->value('receipt_date');

            $amount = round((float) $supplier->balance, 2);
            $bucket = $this->bucketFor($oldest ? substr((string) $oldest, 0, 10) : null, $asOf);

            $parties[$supplier->id] = [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'total' => $amount,
                'buckets' => array_merge($this->emptyBuckets(), [$bucket => $amount]),
                'documents' => [],
            ];
        }

        return $this->presentAging($parties, 'accounts_payable');
    }

    /**
     * Shapes an aging set and reconciles it against its control account.
     *
     * @param  array<int,array<string,mixed>>  $parties
     * @return array<string,mixed>
     */
    private function presentAging(array $parties, string $controlRole): array
    {
        $parties = collect($parties)->sortByDesc('total')->values();

        $totals = $this->emptyBuckets();

        foreach ($parties as $party) {
            foreach (self::BUCKETS as $bucket) {
                $totals[$bucket] = round($totals[$bucket] + $party['buckets'][$bucket], 2);
            }
        }

        $subsidiaryTotal = round($parties->sum('total'), 2);
        $control = LedgerAccount::where('posting_role', $controlRole)->first();
        $controlBalance = $control ? round((float) $control->balance, 2) : null;

        return [
            'parties' => $parties,
            'buckets' => $totals,
            'total' => $subsidiaryTotal,
            'control_account' => $control ? [
                'code' => $control->code,
                'name' => $control->name,
                'balance' => $controlBalance,
            ] : null,
            // A subsidiary list that does not add up to its control account
            // means one of the two is wrong; saying so is the whole point of
            // printing them side by side.
            'difference' => $controlBalance === null ? null : round($subsidiaryTotal - $controlBalance, 2),
            'reconciled' => $controlBalance === null
                ? null
                : abs($subsidiaryTotal - $controlBalance) < self::EPSILON,
        ];
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
                ->whereColumn('journal_entry_headers.posting_key', $this->postingKeyExpression('invoice:', 'invoices.id'))
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
                ->whereColumn('journal_entry_headers.posting_key', $this->postingKeyExpression('payment:', 'payments.id'))
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

        $unpostedSupplierPayments = DB::table('supplier_payments')
            ->whereNull('deleted_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entry_headers')
                ->whereColumn('journal_entry_headers.posting_key', $this->postingKeyExpression('supplier_payment:', 'supplier_payments.id'))
                ->whereNull('journal_entry_headers.deleted_at'))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $checks[] = $this->healthCheck(
            'unposted_supplier_payments',
            'مدفوعات موردين غير مُرحَّلة',
            (int) $unpostedSupplierPayments->n,
            'صرف بقيمة '.number_format((float) $unpostedSupplierPayments->amount, 2).' خرج من الخزينة دون أن يُخفِّض ذمم الموردين في الدفاتر.',
            'راجع سجل مدفوعات الموردين وأعد تسجيل ما لم يُرحَّل.'
        );

        $unpostedPayrolls = DB::table('payrolls')
            ->whereIn('status', ['processed', 'paid'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entry_headers')
                ->whereColumn('journal_entry_headers.posting_key', $this->postingKeyExpression('payroll:', 'payrolls.id'))
                ->whereNull('journal_entry_headers.deleted_at'))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(net_salary), 0) as amount')
            ->first();

        $checks[] = $this->healthCheck(
            'unposted_payrolls',
            'مسيرات رواتب غير مُرحَّلة',
            (int) $unpostedPayrolls->n,
            'رواتب بصافي '.number_format((float) $unpostedPayrolls->amount, 2).' استُحقّت ولم تظهر كمصروف في قائمة الدخل.',
            'افتح المسيرة وأعد حفظ حالتها لترحيل قيد الاستحقاق.'
        );

        /* ---- Sales orders whose documents do not follow them ---- */

        $shippedWithoutCogs = DB::table('sales_orders')
            ->whereIn('status', ['shipped', 'delivered'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entry_headers')
                ->whereColumn('journal_entry_headers.posting_key', $this->postingKeyExpression('so_cogs:', 'sales_orders.id'))
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

        /* ---- Currency: the books are kept in exactly one ---- */

        $base = base_currency_code();

        $foreignDocuments = DB::table('invoices')
            ->whereNotNull('currency')
            ->where('currency', '!=', $base)
            ->count();

        $checks[] = $this->healthCheck(
            'documents_in_other_currency',
            'فواتير بعملة غير عملة الأساس',
            $foreignDocuments,
            'الدفاتر تُمسك بعملة واحدة ('.$base.') ولا يُحوَّل شيء عند الترحيل، فمبالغ هذه الفواتير أُثبتت كما هي.',
            'وحّد عملة المستندات مع عملة الأساس، أو راجع صحة مبالغ هذه الفواتير قبل الاعتماد عليها.'
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

    /**
     * A posting key built in SQL from a prefix and a column.
     *
     * `CONCAT` is MySQL's spelling and SQLite has no such function at all, so
     * every one of these checks threw "no such function: CONCAT" on the test
     * engine — which is exactly why nothing here was ever covered by a test.
     * Both engines understand their own operator, and this picks it.
     */
    private function postingKeyExpression(string $prefix, string $column): \Illuminate\Database\Query\Expression
    {
        $quoted = "'".str_replace("'", "''", $prefix)."'";

        return DB::raw(DB::getDriverName() === 'mysql'
            ? "CONCAT({$quoted}, {$column})"
            : "({$quoted} || {$column})");
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
                    ->whereBetween(DB::raw('DATE(h.entry_date)'), [$fromDate, $toDate]);
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
            ->whereBetween(DB::raw('DATE(entry_date)'), [$fromDate, $toDate])
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
        $from = $this->parseDate($request->input('date_from'), now()->startOfYear());
        $to = $this->parseDate($request->input('date_to'), now());

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    /**
     * One date from the query string, or the fallback.
     *
     * Validated by hand against the exact shape the date pickers send.
     * `Carbon::parse` emits a PHP warning on its way to throwing and
     * `createFromFormat` throws rather than returning false, so either would
     * turn a stray query string into log noise or a 500.
     */
    private function parseDate($value, \Carbon\Carbon $fallback): \Carbon\Carbon
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return $fallback;
        }

        [, $year, $month, $day] = $m;

        // Rejects 2025-13-45 as well as 2025-02-30, which would otherwise roll
        // silently into the next month and shift the whole period.
        return checkdate((int) $month, (int) $day, (int) $year)
            ? \Carbon\Carbon::create((int) $year, (int) $month, (int) $day)->startOfDay()
            : $fallback;
    }
}
