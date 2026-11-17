<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCommission extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'month',
        'commission_rate',
        'gross_sales',
        'total_returns',
        'total_sales',
        'extra_expenses',
        'withdrawals',
        'monthly_target',
        'notes',
        'created_by',
        'deleted_by',
    ];

    protected $casts = [
        'month' => 'date',
        'commission_rate' => 'decimal:2',
        'gross_sales' => 'decimal:5',
        'total_returns' => 'decimal:5',
        'total_sales' => 'decimal:5',
        'extra_expenses' => 'decimal:5',
        'withdrawals' => 'decimal:5',
        'monthly_target' => 'decimal:5',
    ];

    const STATUS_CREDIT = 'creditor';
    const STATUS_DEBIT = 'debtor';
    const STATUS_BALANCED = 'balanced';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function withdrawalTransactions(): HasMany
    {
        return $this->hasMany(EmployeeCommissionWithdrawal::class)->orderBy('withdrawn_at');
    }

    /**
     * Re-sums `withdrawals` from the transaction ledger and persists it.
     *
     * `withdrawals` stays a plain column — read everywhere else in this model
     * — rather than a computed accessor, so a listing of many months does not
     * pay for a relation load per row. The transaction endpoints call this
     * after every add/edit/delete, which is the only place the ledger changes.
     */
    public function recalculateWithdrawals(): void
    {
        $this->withdrawals = $this->withdrawalTransactions()->sum('base_amount');
        $this->save();
    }

    /** Per-currency rollup of the withdrawal ledger, for the statement and print view. */
    public function currencyBreakdown(): array
    {
        return $this->withdrawalTransactions()
            // withdrawalTransactions() carries its own `order by withdrawn_at`
            // for the transaction list; a GROUP BY query needs that cleared; a
            // reader stuck on top of the old order violates ONLY_FULL_GROUP_BY
            // since withdrawn_at is neither grouped nor aggregated.
            ->reorder()
            ->selectRaw('currency_code, COUNT(*) as count, SUM(amount) as total_amount, SUM(base_amount) as total_base_amount')
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get()
            ->map(fn ($row) => [
                'currency_code' => $row->currency_code,
                'count' => (int) $row->count,
                'total_amount' => (float) $row->total_amount,
                'total_base_amount' => (float) $row->total_base_amount,
            ])
            ->all();
    }

    /** Statuses in the invoices table that count as an actual sale. */
    public static function revenueRecognizedStatuses(): array
    {
        return [
            Invoice::STATUS_CONFIRMED,
            Invoice::STATUS_PROCESSING,
            Invoice::STATUS_SHIPPED,
            Invoice::STATUS_DELIVERED,
        ];
    }

    /**
     * Sums the total returns attributed to this employee within the given month.
     * Includes credit notes from RMA returns or manual adjustments, as well as
     * any completed returns without separate credit notes.
     *
     * @return array{total_returns: float, return_count: int, returns: list<array<string, mixed>>}
     */
    public static function computeReturnsForMonth(int $employeeId, \DateTimeInterface $month): array
    {
        $start = \Illuminate\Support\Carbon::parse($month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $creditNotesQuery = CreditNote::query()
            ->with(['customer:id,name', 'invoice:id,invoice_number'])
            ->where('status', '!=', CreditNote::STATUS_CANCELLED)
            ->where(function ($dateQ) use ($start, $end) {
                $dateQ->whereBetween('issue_date', [$start, $end])
                    ->orWhere(function ($fallbackQ) use ($start, $end) {
                        $fallbackQ->whereNull('issue_date')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->where(function ($q) use ($employeeId) {
                $q->whereHas('invoice', function ($iq) use ($employeeId) {
                    $iq->where('assigned_employee_id', $employeeId);
                })->orWhere(function ($noInvoice) use ($employeeId) {
                    $noInvoice->whereNull('invoice_id')
                        ->where(function ($rmaOrCustomer) use ($employeeId) {
                            $rmaOrCustomer->whereHas('rmaRequest', function ($rq) use ($employeeId) {
                                $rq->whereHas('invoice', fn ($iq) => $iq->where('assigned_employee_id', $employeeId))
                                    ->orWhereHas('salesOrder', fn ($sq) => $sq->where('assigned_employee_id', $employeeId));
                            })->orWhere(function ($directCustomer) use ($employeeId) {
                                $directCustomer->whereNull('rma_request_id')
                                    ->whereHas('customer', fn ($cq) => $cq->where('employee_id', $employeeId));
                            });
                        });
                });
            });

        $creditNotes = $creditNotesQuery->get();
        $creditNotesTotal = (float) $creditNotes->sum('total');

        $returnsList = $creditNotes->map(fn (CreditNote $cn) => [
            'id' => $cn->id,
            'type' => 'credit_note',
            'number' => $cn->credit_note_number,
            'date' => $cn->issue_date?->toDateString() ?: $cn->created_at?->toDateString(),
            'amount' => (float) $cn->total,
            'reason' => $cn->reason ?: 'مرتجع',
            'customer_name' => $cn->customer?->name,
            'invoice_number' => $cn->invoice?->invoice_number,
        ])->all();

        // Check for completed RMA returns that do not have a credit note
        $orphanRmaQuery = RmaRequest::query()
            ->with(['customer:id,name', 'invoice:id,invoice_number'])
            ->where('status', RmaRequest::STATUS_COMPLETED)
            ->whereDoesntHave('creditNotes')
            ->where(function ($dateQ) use ($start, $end) {
                $dateQ->whereBetween('completed_at', [$start, $end])
                    ->orWhere(function ($fb) use ($start, $end) {
                        $fb->whereNull('completed_at')->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->where(function ($q) use ($employeeId) {
                $q->whereHas('invoice', fn ($iq) => $iq->where('assigned_employee_id', $employeeId))
                    ->orWhereHas('salesOrder', fn ($sq) => $sq->where('assigned_employee_id', $employeeId))
                    ->orWhere(function ($cq) use ($employeeId) {
                        $cq->whereNull('invoice_id')
                            ->whereNull('sales_order_id')
                            ->whereHas('customer', fn ($custQ) => $custQ->where('employee_id', $employeeId));
                    });
            });

        $orphanRmas = $orphanRmaQuery->get();
        $orphanRmaTotal = (float) $orphanRmas->sum('refund_amount');

        foreach ($orphanRmas as $rma) {
            $returnsList[] = [
                'id' => $rma->id,
                'type' => 'rma',
                'number' => $rma->rma_number,
                'date' => $rma->completed_at?->toDateString() ?: $rma->requested_at?->toDateString(),
                'amount' => (float) $rma->refund_amount,
                'reason' => $rma->reason_description ?: 'مرتجع RMA',
                'customer_name' => $rma->customer?->name,
                'invoice_number' => $rma->invoice?->invoice_number,
            ];
        }

        return [
            'total_returns' => round($creditNotesTotal + $orphanRmaTotal, 2),
            'return_count' => count($returnsList),
            'returns' => $returnsList,
        ];
    }

    /**
     * Sums the recognised revenue and returns credited to this employee
     * within the given month, computing net sales.
     */
    public static function computeSalesForMonth(int $employeeId, \DateTimeInterface $month): array
    {
        $start = \Illuminate\Support\Carbon::parse($month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $query = Invoice::where('assigned_employee_id', $employeeId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', self::revenueRecognizedStatuses());

        $grossSales = (float) $query->sum('total');
        $invoiceCount = (int) $query->count();

        $returnsData = self::computeReturnsForMonth($employeeId, $month);
        $totalReturns = $returnsData['total_returns'];
        $netSales = round(max(0, $grossSales - $totalReturns), 2);

        return [
            'gross_sales' => $grossSales,
            'total_returns' => $totalReturns,
            'net_sales' => $netSales,
            'total_sales' => $netSales,
            'invoice_count' => $invoiceCount,
            'return_count' => $returnsData['return_count'],
            'returns' => $returnsData['returns'],
        ];
    }

    public function commissionAmount(): float
    {
        return round((float) $this->total_sales * (float) $this->commission_rate / 100, 2);
    }

    public function netDue(): float
    {
        return round($this->commissionAmount() - (float) $this->extra_expenses, 2);
    }

    public function balance(): float
    {
        return round($this->netDue() - (float) $this->withdrawals, 2);
    }

    public function balanceStatus(): string
    {
        $balance = $this->balance();
        if ($balance > 0.009) {
            return self::STATUS_CREDIT;
        }
        if ($balance < -0.009) {
            return self::STATUS_DEBIT;
        }
        return self::STATUS_BALANCED;
    }

    /** Percentage of the monthly target reached, or null when no target was set. */
    public function achievementRate(): ?float
    {
        if (empty($this->monthly_target) || (float) $this->monthly_target <= 0) {
            return null;
        }
        return round((float) $this->total_sales / (float) $this->monthly_target * 100, 2);
    }

    public function toStatement(): array
    {
        $grossSales = (float) ($this->gross_sales ?: $this->total_sales);
        $totalReturns = (float) ($this->total_returns ?? 0);
        $netSales = round(max(0, (float) $this->total_sales), 2);

        return array_merge($this->toArray(), [
            'gross_sales' => $grossSales,
            'total_returns' => $totalReturns,
            'net_sales' => $netSales,
            'commission_amount' => $this->commissionAmount(),
            'net_due' => $this->netDue(),
            'balance' => $this->balance(),
            'balance_status' => $this->balanceStatus(),
            'achievement_rate' => $this->achievementRate(),
        ]);
    }
}
