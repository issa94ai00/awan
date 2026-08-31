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
        'total_sales' => 'decimal:2',
        'extra_expenses' => 'decimal:2',
        'withdrawals' => 'decimal:2',
        'monthly_target' => 'decimal:2',
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
     * Sums the recognised revenue of every invoice credited to this employee
     * within the given month. Mirrors Invoice::getRecognizedRevenue() as a
     * query so it can be aggregated in SQL instead of hydrating every row.
     */
    public static function computeSalesForMonth(int $employeeId, \DateTimeInterface $month): array
    {
        $start = \Illuminate\Support\Carbon::parse($month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $query = Invoice::where('assigned_employee_id', $employeeId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', self::revenueRecognizedStatuses());

        return [
            'total_sales' => (float) $query->sum('total'),
            'invoice_count' => (int) $query->count(),
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
        return array_merge($this->toArray(), [
            'commission_amount' => $this->commissionAmount(),
            'net_due' => $this->netDue(),
            'balance' => $this->balance(),
            'balance_status' => $this->balanceStatus(),
            'achievement_rate' => $this->achievementRate(),
        ]);
    }
}
