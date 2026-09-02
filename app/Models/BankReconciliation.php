<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * One proving of a bank account against the bank's own statement.
 *
 * The bank balance is the only figure in the books with an independent witness.
 * A reconciliation is what asks it: every movement either cleared or is still
 * in transit, and the arithmetic closes or something is wrong.
 */
class BankReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'account_id',
        'statement_date',
        'statement_balance',
        'status',
        'completed_at',
        'completed_by',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_balance' => 'decimal:5',
        'completed_at' => 'datetime',
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    /** Entry statuses that never reached the books. */
    private const UNPOSTED_STATUSES = ['draft', 'pending', 'void', 'cancelled'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function clearedLines(): BelongsToMany
    {
        return $this->belongsToMany(
            JournalEntryLine::class,
            'bank_reconciliation_lines',
            'bank_reconciliation_id',
            'journal_entry_line_id'
        )->withTimestamps();
    }

    /**
     * Every movement on the account up to the statement date, with whether the
     * bank has seen it yet.
     *
     * Movements after the statement date are deliberately excluded: they cannot
     * appear on a statement that was printed before them, so including them
     * would make every reconciliation fail by the amount of next week's trading.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function movements()
    {
        $cleared = $this->clearedLines()->pluck('journal_entry_lines.id')->all();

        return DB::table('journal_entry_lines as l')
            ->join('journal_entry_headers as h', 'h.id', '=', 'l.journal_entry_header_id')
            ->where('l.account_id', $this->account_id)
            ->whereNull('h.deleted_at')
            ->whereNotIn('h.status', self::UNPOSTED_STATUSES)
            ->whereDate('h.entry_date', '<=', $this->statement_date)
            ->orderBy('h.entry_date')
            ->orderBy('l.id')
            ->select([
                'l.id', 'l.debit', 'l.credit', 'l.description as line_description',
                'h.entry_number', 'h.entry_date', 'h.description', 'h.source_module',
            ])
            ->get()
            ->map(function ($row) use ($cleared) {
                $row->amount = round((float) $row->debit - (float) $row->credit, 2);
                $row->is_cleared = in_array((int) $row->id, $cleared, true);

                return $row;
            });
    }

    /**
     * The arithmetic that either closes or names a problem.
     *
     *     book balance − still outstanding = statement balance
     *
     * @return array<string,float|bool>
     */
    public function summary(): array
    {
        $movements = $this->movements();

        $book = round($movements->sum('amount'), 2);
        $outstanding = round($movements->reject(fn ($row) => $row->is_cleared)->sum('amount'), 2);
        $statement = round((float) $this->statement_balance, 2);

        $difference = round($book - $outstanding - $statement, 2);

        return [
            'book_balance' => $book,
            'cleared_total' => round($book - $outstanding, 2),
            'outstanding_total' => $outstanding,
            'statement_balance' => $statement,
            'difference' => $difference,
            // Anything left over is not timing — it is an error in one of the
            // two records, and the only useful thing to do is say so.
            'is_reconciled' => abs($difference) < 0.005,
        ];
    }
}
