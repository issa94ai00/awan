<?php

namespace App\Console\Commands;

use App\Models\Quote;
use Illuminate\Console\Command;

/**
 * Closes sent quotes whose validity date has passed.
 *
 * A quote past valid_until can no longer be accepted (QuoteController refuses
 * it), yet it stayed "sent" forever: the follow-up list filled with offers
 * nobody could act on and the quote→order conversion rate read lower than it
 * was. This applies the same sent → expired move a user would make, through
 * the model's own transition rules. Drafts are left alone (draft → expired is
 * not an allowed move; a draft was never offered to anyone), and a quote that
 * already became a sales order never changes status.
 *
 * Expired is not final: the app allows expired → draft, so a quote can be
 * revived and re-sent with a new date.
 */
class ExpireQuotes extends Command
{
    protected $signature = 'quotes:expire {--dry-run : List what would expire without changing anything}';

    protected $description = 'Mark sent quotes past their valid_until date as expired';

    public function handle(): int
    {
        $quotes = Quote::where('status', Quote::STATUS_SENT)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->whereDoesntHave('salesOrder')
            ->get();

        $expired = 0;
        foreach ($quotes as $quote) {
            if (! $quote->canMoveTo(Quote::STATUS_EXPIRED)) {
                continue;
            }
            $this->line(($this->option('dry-run') ? '[dry-run] ' : '')."{$quote->quote_number} (valid until {$quote->valid_until->toDateString()})");
            if (! $this->option('dry-run')) {
                $quote->update(['status' => Quote::STATUS_EXPIRED]);
            }
            $expired++;
        }

        $this->info(($this->option('dry-run') ? 'Would expire' : 'Expired')." {$expired} quote(s).");

        return self::SUCCESS;
    }
}
