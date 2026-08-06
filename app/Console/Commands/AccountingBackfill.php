<?php

namespace App\Console\Commands;

use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\Payment;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Console\Command;

/**
 * Posts historical documents that predate automatic ledger posting.
 *
 * Invoices, payments, credit notes and expenses created before the posting
 * service existed never reached the general ledger, so the trial balance and
 * the financial statements only describe part of the business. This walks the
 * documents and posts whatever is missing.
 *
 * Safe to re-run: posting is keyed on the document, so anything already in the
 * ledger is skipped rather than duplicated. Defaults to a dry run — posting to
 * the books is not something to do by accident.
 */
class AccountingBackfill extends Command
{
    protected $signature = 'accounting:backfill
                            {--apply : Actually post (without this the command only reports)}
                            {--type=* : Limit to invoices, payments, credit-notes or expenses}
                            {--from= : Only documents dated on or after this date}
                            {--repost : Replace existing entries that do not balance}';

    protected $description = 'Post historical documents to the general ledger (idempotent)';

    public function handle(LedgerPostingService $ledger): int
    {
        $apply = (bool) $this->option('apply');
        $types = $this->option('type') ?: ['invoices', 'payments', 'credit-notes', 'expenses'];
        $from = $this->option('from');

        if (!$apply) {
            $this->warn('وضع المعاينة — لن يُكتب أي قيد. أضف --apply للتنفيذ.');
            $this->newLine();
        }

        $summary = [];

        foreach ($types as $type) {
            $summary[] = match ($type) {
                'invoices' => $this->postBatch('الفواتير', $this->invoices($from), fn ($d) => $ledger->postInvoice($d), $apply),
                'payments' => $this->postBatch('المدفوعات', $this->payments($from), fn ($d) => $ledger->postPayment($d), $apply),
                'credit-notes' => $this->postBatch('الإشعارات الدائنة', $this->creditNotes($from), fn ($d) => $ledger->postCreditNote($d), $apply),
                'expenses' => $this->postBatch('المصاريف', $this->expenses($from), fn ($d) => $ledger->postExpense($d), $apply),
                default => throw new \InvalidArgumentException("نوع غير معروف: {$type}"),
            };
        }

        $this->newLine();
        $this->table(['المستند', 'إجمالي', 'مُرحَّل مسبقاً', 'سيُرحَّل', 'أخطاء'], $summary);

        if (!$apply) {
            $this->newLine();
            $this->line('أعد التشغيل مع --apply للترحيل، ثم شغّل accounting:check للتحقق.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection  $documents
     * @return array{0:string,1:int,2:int,3:int,4:int}
     */
    private function postBatch(string $label, $documents, callable $post, bool $apply): array
    {
        $already = 0;
        $pending = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($documents->count());
        $bar->setFormat("  {$label}: %current%/%max% [%bar%]");
        $bar->start();

        foreach ($documents as $document) {
            $key = $this->keyFor($document);
            $existing = $key ? JournalEntryHeader::where('posting_key', $key)->first() : null;

            if ($existing) {
                // A malformed entry — one whose own lines do not add up — is not
                // a valid accounting record, so with --repost it is discarded and
                // rewritten from the document. Entries that balance are never
                // touched, however wrong somebody might think they are: changing
                // those is a correcting-entry decision, not a data migration.
                if (!$this->option('repost') || $this->isBalanced($existing)) {
                    $already++;
                    $bar->advance();
                    continue;
                }

                if ($apply) {
                    $this->discard($existing);
                }
            }

            $pending++;

            if ($apply) {
                try {
                    $post($document);
                } catch (\Throwable $e) {
                    $errors++;
                    $pending--;
                    $this->newLine();
                    $this->error("  {$key}: {$e->getMessage()}");
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return [$label, $documents->count(), $already, $pending, $errors];
    }

    private function isBalanced(JournalEntryHeader $header): bool
    {
        $debit = (float) $header->lines()->sum('debit');
        $credit = (float) $header->lines()->sum('credit');

        return abs($debit - $credit) < 0.005;
    }

    /**
     * Removes an invalid entry and unwinds its effect on the cached account
     * balances, so the replacement posting starts from a clean position.
     */
    private function discard(JournalEntryHeader $header): void
    {
        foreach ($header->lines()->with('ledgerAccount')->get() as $line) {
            $account = $line->ledgerAccount;
            if (!$account) {
                continue;
            }

            $delta = \App\Models\LedgerAccount::signedDelta(
                $account->type,
                (float) $line->debit,
                (float) $line->credit
            );
            $account->increment('balance', -$delta);
        }

        $header->lines()->delete();
        $header->forceDelete();
    }

    private function keyFor($document): ?string
    {
        return match (true) {
            $document instanceof Invoice => 'invoice:' . $document->id,
            $document instanceof Payment => 'payment:' . $document->id,
            $document instanceof CreditNote => 'credit_note:' . $document->id,
            $document instanceof Expense => 'expense:' . $document->id,
            default => null,
        };
    }

    private function invoices(?string $from)
    {
        return Invoice::query()
            ->where('status', '!=', 'cancelled')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->orderBy('id')
            ->get();
    }

    private function payments(?string $from)
    {
        return Payment::query()
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->orderBy('id')
            ->get();
    }

    private function creditNotes(?string $from)
    {
        return CreditNote::query()
            ->where('status', '!=', CreditNote::STATUS_CANCELLED)
            ->when($from, fn ($q) => $q->whereDate('issue_date', '>=', $from))
            ->orderBy('id')
            ->get();
    }

    private function expenses(?string $from)
    {
        return Expense::query()
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->orderBy('id')
            ->get();
    }
}
